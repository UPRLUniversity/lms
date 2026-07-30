<?php

namespace Tests\Feature\Public;

use App\Models\Cart;
use App\Models\Course;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The end-to-end signed-out journey: homepage → programme → course → cart → checkout,
 * with the login wall standing in exactly one place and never losing the basket.
 */
class GuestJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PaymentMethod::factory()->create();     // sandbox, enabled
    }

    private function paidCourse(string $title = 'Principles of Public Relations'): Course
    {
        $programme = Programme::factory()->create([
            'code' => 'CPR',
            'slug' => 'cpr',
            'name' => 'Professional Certificate in Public Relations',
            'per_paper_fee' => 7000,
            'registration_fee' => 20000,
            'administration_fee' => 25000,
        ]);

        $course = Course::factory()->published()->create(['title' => $title, 'is_free' => false]);
        $course->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->named('Part I', 1)->create()->id,
            ['credit_load' => 4, 'is_primary' => true],
        );

        return $course->load('programmeParts.programme');
    }

    public function test_a_guest_can_walk_from_the_homepage_to_a_course_without_signing_in(): void
    {
        $course = $this->paidCourse();
        $programme = $course->primaryProgramme();

        $this->get(route('home'))->assertOk()->assertSee(route('programmes.show', $programme));
        $this->get(route('programmes.show', $programme))->assertOk()->assertSee($course->title);
        $this->get(route('catalogue.index'))->assertOk()->assertSee($course->title);
        $this->get(route('catalogue.show', $course))
            ->assertOk()
            ->assertSee('Add to cart')
            ->assertSee('₦7,000');
    }

    public function test_a_guest_reaches_the_checkout_and_is_asked_to_log_in_there_rather_than_bounced(): void
    {
        $course = $this->paidCourse();

        // "Buy now" carries a guest straight through to the checkout entry.
        $this->post(route('cart.store', $course), ['then' => 'checkout'])
            ->assertRedirect(route('checkout.show'));

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Log in to continue')
            // The order is still in front of them, priced exactly as it will be charged.
            ->assertSee($course->title)
            ->assertSee('₦7,000')
            ->assertSee('Professional Certificate in Public Relations — Registration fee')
            ->assertSee('₦52,000')     // 7,000 + 20,000 + 25,000
            ->assertSee(route('login'))
            ->assertSee(route('register'));

        // Nothing was written: an order still needs an account.
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_logging_in_from_the_checkout_returns_the_guest_to_the_checkout_with_the_cart_intact(): void
    {
        $course = $this->paidCourse();
        $user = User::factory()->create();

        $this->post(route('cart.store', $course));
        $this->assertDatabaseHas('carts', ['user_id' => null]);

        // Visiting the checkout records the intended destination…
        $this->get(route('checkout.show'))->assertOk();

        // …so signing in lands back on it rather than the dashboard.
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('checkout.show'));

        // And the basket came with them.
        $cart = Cart::where('user_id', $user->id)->with('items')->first();
        $this->assertNotNull($cart);
        $this->assertCount(1, $cart->items);
        $this->assertSame($course->id, $cart->items->first()->course_id);
        $this->assertDatabaseMissing('carts', ['user_id' => null]);

        // The real checkout now renders, with a payment method to choose.
        $this->actingAs($user)->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Payment method')
            ->assertSee($course->title)
            ->assertDontSee('Log in to continue');
    }

    public function test_a_guest_still_cannot_place_an_order(): void
    {
        // Reading the checkout is open; writing an order is not.
        $course = $this->paidCourse();
        $this->post(route('cart.store', $course));

        $this->post(route('checkout.store'), ['payment_method' => 'sandbox', 'terms' => '1'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_an_empty_cart_sends_a_guest_back_to_the_cart_rather_than_an_empty_checkout(): void
    {
        $this->get(route('checkout.show'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');
    }

    public function test_a_guest_can_preview_a_free_lesson_and_is_only_asked_for_an_account_to_enrol(): void
    {
        // A free course needs no cart at all — but it does need an account, and the ask
        // happens on the course page rather than by bouncing the visitor away.
        $course = Course::factory()->published()->create(['title' => 'Introduction to Leadership']);

        $this->get(route('catalogue.show', $course))
            ->assertOk()
            ->assertSee('Introduction to Leadership')
            ->assertSee('Create an account to enrol')
            ->assertSee(route('login'));

        // The learning player itself stays shut to strangers.
        $this->get(route('learn.resume', $course))->assertRedirect(route('login'));
    }
}
