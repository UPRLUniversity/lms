<?php

namespace App\Services\Commerce;

use App\Enums\EnrollmentSource;
use App\Enums\OrderStatus;
use App\Exceptions\EnrollmentException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\OrderPaidNotification;
use App\Services\Courses\EnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place an order becomes access.
 *
 * Everything here must be safe to run twice. Payment gateways retry webhooks, a buyer
 * may refresh the callback URL, and an admin may confirm a bank transfer that a
 * webhook already confirmed — so markPaid() short-circuits on an already-paid order
 * and enrolment tolerates the student already being on the course.
 *
 * Enrolment goes through the existing EnrollmentService rather than writing
 * enrollments directly, so capacity locking, waitlisting and notifications behave
 * exactly as they do for every other enrolment path. A purchase is recorded with
 * EnrollmentSource::Purchase for the roster's audit trail.
 */
class OrderFulfilmentService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly CouponService $coupons,
        private readonly CartService $carts,
    ) {}

    /**
     * Mark an order paid and grant everything it bought.
     *
     * @param  array<string, mixed>  $gatewayPayload
     * @return bool true if this call transitioned the order, false if it was already paid
     */
    public function markPaid(Order $order, ?string $gatewayReference = null, array $gatewayPayload = []): bool
    {
        $transitioned = DB::transaction(function () use ($order, $gatewayReference, $gatewayPayload) {
            // Re-read under a lock: two concurrent webhooks for the same order must not
            // both decide they are the one doing the transition.
            $fresh = Order::query()->lockForUpdate()->find($order->id);

            if ($fresh === null || $fresh->status === OrderStatus::Paid) {
                return false;
            }

            $fresh->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'gateway_reference' => $gatewayReference ?? $fresh->gateway_reference,
                'gateway_payload' => $gatewayPayload ?: $fresh->gateway_payload,
            ]);

            // Only now does the coupon actually count against its allowance.
            $this->coupons->redeem($fresh);

            $order->setRawAttributes($fresh->getAttributes());
            $order->syncOriginal();

            return true;
        });

        if (! $transitioned) {
            return false;
        }

        // Outside the transaction: enrolment takes its own course-level locks, and a
        // notification must not be sent from inside a transaction that could roll back.
        $this->grantAccess($order);
        $this->notify($order);
        $this->emptyCart($order);

        return true;
    }

    /**
     * Enrol the buyer on every course the order bought.
     *
     * Failures are logged, not thrown: the money has already been taken, so aborting
     * here would leave a paid order with no access and no record of why. A course that
     * cannot be granted (deleted, or full for a reason capacity cannot resolve) is
     * surfaced in the log for staff to fix by hand.
     */
    public function grantAccess(Order $order): void
    {
        $order->loadMissing(['items.course', 'user']);

        foreach ($order->courseItems() as $item) {
            $course = $item->course;

            if ($course === null) {
                Log::warning('Paid order references a deleted course.', [
                    'order' => $order->reference,
                    'order_item' => $item->id,
                ]);

                continue;
            }

            try {
                $this->enrollments->adminEnroll(
                    student: $order->user,
                    course: $course,
                    actor: $order->user,
                    source: EnrollmentSource::Purchase,
                );
            } catch (EnrollmentException $e) {
                // alreadyEnrolled is the expected outcome of a replayed webhook, and is
                // success as far as the buyer is concerned.
                Log::info('Purchase enrolment skipped.', [
                    'order' => $order->reference,
                    'course' => $course->code,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Record a failed or abandoned payment. Never grants anything.
     *
     * @param  array<string, mixed>  $gatewayPayload
     */
    public function markFailed(Order $order, array $gatewayPayload = []): void
    {
        if ($order->status === OrderStatus::Paid) {
            return;   // a late failure notice must not revoke a completed purchase
        }

        $order->update([
            'status' => OrderStatus::Failed,
            'gateway_payload' => $gatewayPayload ?: $order->gateway_payload,
        ]);
    }

    /**
     * Park an order pending an offline payment (bank transfer).
     */
    public function markAwaitingPayment(Order $order): void
    {
        if ($order->status->isOpen()) {
            $order->update(['status' => OrderStatus::AwaitingPayment]);
        }
    }

    /**
     * Record a refund. Access is withdrawn with the money — OrderStatus::Refunded does
     * not grant access — but the enrolment itself is left in place deliberately: a
     * student's completed work and grades are not ours to delete, and withdrawing them
     * is a decision for staff, not a side effect of a bookkeeping entry.
     */
    public function markRefunded(Order $order, ?string $note = null): void
    {
        $order->update([
            'status' => OrderStatus::Refunded,
            'admin_note' => $note,
        ]);
    }

    private function notify(Order $order): void
    {
        $order->user?->notify(new OrderPaidNotification($order));
    }

    /**
     * Clear the bought courses out of the buyer's cart. Uses pruneUnbuyable rather
     * than clear() so anything they added after checkout survives.
     */
    private function emptyCart(Order $order): void
    {
        $cart = $order->user ? $this->carts->existing($order->user) : null;

        if ($cart) {
            $this->carts->pruneUnbuyable($cart, $order->user);
        }
    }
}
