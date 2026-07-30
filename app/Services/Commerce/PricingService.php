<?php

namespace App\Services\Commerce;

use App\Enums\OrderItemKind;
use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Course;
use App\Models\Order;
use App\Models\Programme;
use App\Models\User;
use App\Support\Commerce\PriceLine;
use Illuminate\Support\Collection;

/**
 * The one place a price is decided.
 *
 * Course price, in order:
 *   1. is_free            → 0
 *   2. price_override      → that amount
 *   3. primary programme's per_paper_fee
 *   4. 0 (treated as free)
 *
 * Why inheritance rather than a price on every course: the published NIPR schedule
 * prices by TIER, not per paper — CPR papers are ₦7,000, DPR ₦10,000, Variant
 * ₦15,000. Copying that onto ~40 courses would be 40 numbers to keep in step with 3.
 * A course that genuinely differs sets price_override.
 *
 * Programme ENTRY fees (registration + administration) are charged once per
 * programme, ever — not per course. They are therefore cart-level lines computed
 * from what the buyer has already paid for, never part of a course's price. This is
 * the single most important thing to get right here: charging registration twice is
 * the kind of bug a student notices and an institution apologises for.
 */
class PricingService
{
    /**
     * The effective price of a course.
     */
    public function priceFor(Course $course): float
    {
        if ($course->is_free) {
            return 0.0;
        }

        if ($course->price_override !== null) {
            return round((float) $course->price_override, 2);
        }

        $perPaper = $course->primaryProgramme()?->per_paper_fee;

        return $perPaper === null ? 0.0 : round((float) $perPaper, 2);
    }

    public function isPaid(Course $course): bool
    {
        return $this->priceFor($course) > 0;
    }

    /**
     * Every line the buyer will be charged for this cart: one per course, then any
     * programme entry fees they have not already paid.
     *
     * @return Collection<int, PriceLine>
     */
    public function linesFor(Cart $cart, ?User $user = null): Collection
    {
        $cart->loadMissing(['items.course.programmeParts.programme']);

        $courses = $cart->items
            ->map(fn ($item) => $item->course)
            ->filter()
            ->values();

        $lines = $courses->map(fn (Course $course) => PriceLine::course($course, $this->priceFor($course)));

        return $lines->concat($this->entryFeeLinesFor($courses, $user));
    }

    /**
     * Entry-fee lines owed for a set of courses.
     *
     * A fee is owed when: the cart contains at least one PAID course from programme P,
     * P actually charges that fee, and the buyer has no previous paid order carrying
     * an entry fee for P.
     *
     * Free courses deliberately do not trigger an entry fee — nobody should be asked
     * for ₦45,000 to start a free taster.
     *
     * A guest ($user === null) is quoted the fees, because we cannot yet know what
     * they have paid. CheckoutService recomputes once they are signed in, so the
     * charge is always correct at the point money moves.
     *
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, PriceLine>
     */
    public function entryFeeLinesFor(Collection $courses, ?User $user = null): Collection
    {
        $alreadyPaid = $user ? $this->programmesWithPaidEntryFee($user) : [];

        $programmes = $courses
            ->filter(fn (Course $course) => $this->isPaid($course))
            ->map(fn (Course $course) => $course->primaryProgramme())
            ->filter()
            ->unique('id')
            ->reject(fn (Programme $programme) => in_array($programme->id, $alreadyPaid, true));

        $lines = collect();

        foreach ($programmes as $programme) {
            if ((float) $programme->registration_fee > 0) {
                $lines->push(PriceLine::fee(
                    OrderItemKind::RegistrationFee,
                    $programme,
                    (float) $programme->registration_fee,
                ));
            }

            if ((float) $programme->administration_fee > 0) {
                $lines->push(PriceLine::fee(
                    OrderItemKind::AdministrationFee,
                    $programme,
                    (float) $programme->administration_fee,
                ));
            }
        }

        return $lines->values();
    }

    /**
     * Programme ids this user has already paid an entry fee for.
     *
     * @return array<int, int>
     */
    public function programmesWithPaidEntryFee(User $user): array
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Paid->value)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('order_items.kind', [
                OrderItemKind::RegistrationFee->value,
                OrderItemKind::AdministrationFee->value,
            ])
            ->whereNotNull('order_items.programme_id')
            ->distinct()
            ->pluck('order_items.programme_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Course ids the user has already bought on a paid order — so the catalogue can
     * say "you own this" and the cart can refuse to sell it twice.
     *
     * @return array<int, int>
     */
    public function purchasedCourseIds(User $user): array
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Paid->value)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.kind', OrderItemKind::Course->value)
            ->whereNotNull('order_items.course_id')
            ->distinct()
            ->pluck('order_items.course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function hasPurchased(User $user, Course $course): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Paid->value)
            ->whereHas('items', fn ($q) => $q
                ->where('kind', OrderItemKind::Course->value)
                ->where('course_id', $course->id))
            ->exists();
    }
}
