<?php

namespace Tests\Feature\Commerce;

use App\Models\Cart;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_add_a_course_to_a_cart(): void
    {
        // The whole point of the public catalogue: interest before commitment.
        $course = Course::factory()->published()->create();

        $this->from(route('catalogue.show', $course))
            ->post(route('cart.store', $course))
            ->assertRedirect();

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('carts', ['user_id' => null]);
    }

    public function test_adding_the_same_course_twice_does_not_duplicate_the_line(): void
    {
        // A course seat has no meaningful quantity.
        $course = Course::factory()->published()->create();

        $this->post(route('cart.store', $course));
        $this->post(route('cart.store', $course));

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_an_unpublished_course_cannot_be_added(): void
    {
        // Otherwise the cart becomes a way to discover drafts.
        foreach ([Course::factory()->draft()->create(), Course::factory()->published()->enrolledOnly()->create()] as $course) {
            $this->post(route('cart.store', $course))->assertNotFound();
        }

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_the_cart_page_renders_for_a_guest(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Crisis Communication']);
        $this->post(route('cart.store', $course));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Crisis Communication')
            ->assertSee('Log in to check out');
    }

    public function test_a_guest_cart_follows_the_visitor_into_their_account_on_login(): void
    {
        // Without this the "add to cart before signing in" affordance is a trap.
        $course = Course::factory()->published()->create();
        $user = User::factory()->create();

        $this->post(route('cart.store', $course));
        $this->assertDatabaseHas('carts', ['user_id' => null]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $cart = Cart::where('user_id', $user->id)->with('items')->first();

        $this->assertNotNull($cart, 'Logging in should produce a cart for the user.');
        $this->assertCount(1, $cart->items);
        $this->assertSame($course->id, $cart->items->first()->course_id);
        // The guest cart is consumed, so the same cookie cannot revive it onto a
        // second account.
        $this->assertDatabaseMissing('carts', ['user_id' => null]);
    }

    public function test_merging_keeps_the_users_own_line_when_both_carts_hold_the_same_course(): void
    {
        $course = Course::factory()->published()->create();
        $user = User::factory()->create();

        $userCart = Cart::factory()->create(['user_id' => $user->id]);
        $userCart->items()->create(['course_id' => $course->id, 'unit_price' => 1234]);

        $this->post(route('cart.store', $course));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $items = Cart::where('user_id', $user->id)->first()->items;

        $this->assertCount(1, $items);
        $this->assertSame('1234.00', $items->first()->unit_price);
    }

    public function test_a_course_can_be_removed(): void
    {
        $course = Course::factory()->published()->create();
        $user = User::factory()->create();

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $item = $cart->items()->create(['course_id' => $course->id, 'unit_price' => 0]);

        $this->actingAs($user)->delete(route('cart.destroy', $item))->assertRedirect();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_cart_item_belonging_to_someone_else_cannot_be_removed(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $course = Course::factory()->published()->create();

        $cart = Cart::factory()->create(['user_id' => $victim->id]);
        $item = $cart->items()->create(['course_id' => $course->id, 'unit_price' => 0]);

        $this->actingAs($attacker)->delete(route('cart.destroy', $item))->assertForbidden();

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_viewing_the_cart_drops_courses_the_buyer_already_owns(): void
    {
        // Between adding and returning, another tab may have paid — the cart must never
        // offer to sell access somebody already has.
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create(['course_id' => $course->id, 'unit_price' => 7000]);

        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        $this->actingAs($user)->get(route('cart.index'))->assertOk()->assertSee('already have access');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_the_cart_can_be_emptied(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cart->items()->create(['course_id' => Course::factory()->published()->create()->id, 'unit_price' => 0]);

        $this->actingAs($user)->delete(route('cart.clear'))->assertRedirect();

        $this->assertDatabaseCount('cart_items', 0);
    }
}
