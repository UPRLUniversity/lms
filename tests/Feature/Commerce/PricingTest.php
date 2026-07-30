<?php

namespace Tests\Feature\Commerce;

use App\Models\Cart;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Services\Commerce\CartService;
use App\Services\Commerce\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(PricingService::class);
    }

    private function paidCourse(float $perPaper = 7000, string $code = 'CPR'): Course
    {
        $programme = Programme::factory()->create([
            'code' => $code,
            'slug' => strtolower($code),
            'per_paper_fee' => $perPaper,
            'registration_fee' => 20000,
            'administration_fee' => 25000,
        ]);

        $course = Course::factory()->published()->create(['is_free' => false]);
        $course->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->create()->id,
            ['is_primary' => true],
        );

        return $course->load('programmeParts.programme');
    }

    public function test_a_course_inherits_its_primary_programmes_per_paper_fee(): void
    {
        $this->assertSame(7000.0, $this->pricing->priceFor($this->paidCourse(7000)));
    }

    public function test_a_price_override_beats_the_programme_fee(): void
    {
        $course = $this->paidCourse(7000);
        $course->update(['price_override' => 25000]);

        $this->assertSame(25000.0, $this->pricing->priceFor($course->fresh()->load('programmeParts.programme')));
    }

    public function test_is_free_beats_everything(): void
    {
        // The safety valve: whatever the programme charges and whatever override is set,
        // a course marked free costs nothing.
        $course = $this->paidCourse(7000);
        $course->update(['is_free' => true, 'price_override' => 99000]);

        $this->assertSame(0.0, $this->pricing->priceFor($course->fresh()->load('programmeParts.programme')));
    }

    public function test_a_course_in_no_programme_is_free(): void
    {
        $course = Course::factory()->published()->create(['is_free' => false]);

        $this->assertSame(0.0, $this->pricing->priceFor($course));
        $this->assertFalse($this->pricing->isPaid($course));
    }

    public function test_courses_default_to_free_so_pre_commerce_courses_are_unaffected(): void
    {
        $this->assertTrue(Course::factory()->create()->is_free);
    }

    public function test_a_cart_is_charged_the_programmes_entry_fees_once(): void
    {
        // The worked example from the design: two CPR papers at 7,000 plus registration
        // 20,000 and administration 25,000 = 59,000 — with the entry fees appearing ONCE,
        // not once per paper.
        $first = $this->paidCourse(7000);
        $programme = $first->primaryProgramme();

        $second = Course::factory()->published()->create(['is_free' => false]);
        $second->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->create()->id,
            ['is_primary' => true],
        );

        $user = User::factory()->create();
        $cart = $this->cartWith($user, [$first, $second]);

        $lines = $this->pricing->linesFor($cart, $user);

        $this->assertSame(4, $lines->count(), 'Two courses and two entry fees.');
        $this->assertSame(59000.0, round($lines->sum(fn ($line) => $line->amount), 2));
        $this->assertSame(2, $lines->filter(fn ($line) => $line->isEntryFee())->count());
    }

    public function test_entry_fees_are_not_charged_twice_across_orders(): void
    {
        $first = $this->paidCourse(7000);
        $programme = $first->primaryProgramme();
        $user = User::factory()->create();

        // They already paid to enter this programme.
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderItem::factory()->registrationFee($programme, 20000)->create(['order_id' => $order->id]);

        $second = Course::factory()->published()->create(['is_free' => false]);
        $second->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->create()->id,
            ['is_primary' => true],
        );

        $lines = $this->pricing->linesFor($this->cartWith($user, [$second]), $user);

        $this->assertSame(1, $lines->count(), 'Only the course — the entry fee was already paid.');
        $this->assertSame(7000.0, round($lines->sum(fn ($line) => $line->amount), 2));
    }

    public function test_two_programmes_in_one_cart_each_charge_their_own_entry_fee(): void
    {
        $cpr = $this->paidCourse(7000, 'CPR');
        $dpr = $this->paidCourse(10000, 'DPR');
        $user = User::factory()->create();

        $lines = $this->pricing->linesFor($this->cartWith($user, [$cpr, $dpr]), $user);

        $this->assertSame(4, $lines->filter(fn ($line) => $line->isEntryFee())->count());
        // 7,000 + 10,000 + (20,000 + 25,000) × 2
        $this->assertSame(107000.0, round($lines->sum(fn ($line) => $line->amount), 2));
    }

    public function test_a_free_course_never_triggers_a_programme_entry_fee(): void
    {
        // Nobody should be asked for 45,000 to start a free taster.
        $course = $this->paidCourse(7000);
        $course->update(['is_free' => true]);
        $user = User::factory()->create();

        $lines = $this->pricing->linesFor($this->cartWith($user, [$course->fresh()]), $user);

        $this->assertSame(1, $lines->count());
        $this->assertSame(0.0, round($lines->sum(fn ($line) => $line->amount), 2));
    }

    public function test_purchase_history_reports_owned_courses(): void
    {
        $course = $this->paidCourse();
        $user = User::factory()->create();

        $this->assertFalse($this->pricing->hasPurchased($user, $course));

        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        $this->assertTrue($this->pricing->hasPurchased($user, $course));
        $this->assertSame([$course->id], $this->pricing->purchasedCourseIds($user));
    }

    public function test_an_unpaid_order_does_not_count_as_a_purchase(): void
    {
        $course = $this->paidCourse();
        $user = User::factory()->create();

        $order = Order::factory()->create(['user_id' => $user->id]);   // pending
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        $this->assertFalse($this->pricing->hasPurchased($user, $course));
    }

    /**
     * @param  array<int, Course>  $courses
     */
    private function cartWith(User $user, array $courses): Cart
    {
        $carts = app(CartService::class);
        $cart = $carts->current($user);

        foreach ($courses as $course) {
            $carts->add($cart, $course);
        }

        return $cart->fresh()->load('items.course.programmeParts.programme');
    }
}
