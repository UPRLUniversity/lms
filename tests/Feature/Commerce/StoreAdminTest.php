<?php

namespace Tests\Feature\Commerce;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /*
    |--------------------------------------------------------------------------
    | Payment methods
    |--------------------------------------------------------------------------
    */

    public function test_only_an_admin_may_reach_the_payment_methods_screen(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->get(route('admin.payment-methods.index'))->assertOk();

        foreach ([Role::Instructor, Role::Student, Role::Auditor] as $role) {
            $this->actingAs($this->userWithRole($role->value))
                ->get(route('admin.payment-methods.index'))
                ->assertForbidden();
        }
    }

    public function test_a_secret_key_is_encrypted_at_rest(): void
    {
        // The most sensitive value in the app. It must not be readable in a database
        // dump or a query log.
        $method = PaymentMethod::factory()->paystack()->create([
            'config' => ['public_key' => 'pk_test_x', 'secret_key' => 'sk_live_TOPSECRET'],
        ]);

        $raw = DB::table('payment_methods')->where('id', $method->id)->value('config');

        $this->assertStringNotContainsString('sk_live_TOPSECRET', $raw);
        $this->assertSame('sk_live_TOPSECRET', $method->fresh()->setting('secret_key'));
    }

    public function test_a_secret_key_is_never_rendered_back_to_the_page(): void
    {
        PaymentMethod::factory()->paystack()->create([
            'config' => ['public_key' => 'pk_test_x', 'secret_key' => 'sk_live_TOPSECRET'],
        ]);

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->get(route('admin.payment-methods.index'))
            ->assertOk()
            ->assertDontSee('sk_live_TOPSECRET');
    }

    public function test_submitting_a_blank_secret_keeps_the_saved_one(): void
    {
        // The field is masked, so an admin changing the environment cannot re-paste a
        // key they cannot see. Blank has to mean "unchanged", not "clear it".
        $method = PaymentMethod::factory()->paystack()->create([
            'config' => ['public_key' => 'pk_test_x', 'secret_key' => 'sk_test_keepme'],
        ]);

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->put(route('admin.payment-methods.update', $method), [
                'label' => 'Paystack',
                'environment' => 'live',
                'config' => ['public_key' => 'pk_live_new', 'secret_key' => ''],
            ])->assertRedirect();

        $method->refresh();

        $this->assertSame('sk_test_keepme', $method->setting('secret_key'));
        $this->assertSame('pk_live_new', $method->setting('public_key'));
        $this->assertTrue($method->isLive());
    }

    public function test_a_crafted_post_cannot_stuff_unknown_keys_into_the_config(): void
    {
        $method = PaymentMethod::factory()->paystack()->create([
            'config' => ['public_key' => '', 'secret_key' => ''],
        ]);

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->put(route('admin.payment-methods.update', $method), [
                'config' => ['secret_key' => 'sk_x', 'evil' => 'payload'],
            ]);

        $this->assertArrayNotHasKey('evil', $method->fresh()->config);
    }

    public function test_a_method_missing_its_configuration_cannot_be_switched_on(): void
    {
        // Offering a keyless gateway at checkout only to fail on submit is worse than
        // not offering it.
        $method = PaymentMethod::factory()->paystack()->disabled()->create([
            'config' => ['public_key' => '', 'secret_key' => ''],
        ]);

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('admin.payment-methods.toggle', $method), ['enable' => 1])
            ->assertSessionHas('error');

        $this->assertFalse($method->fresh()->is_enabled);
    }

    public function test_a_configured_method_can_be_switched_on_and_off(): void
    {
        $method = PaymentMethod::factory()->paystack()->disabled()->create();
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.payment-methods.toggle', $method), ['enable' => 1]);
        $this->assertTrue($method->fresh()->is_enabled);

        $this->actingAs($admin)->post(route('admin.payment-methods.toggle', $method), ['enable' => 0]);
        $this->assertFalse($method->fresh()->is_enabled);
    }

    public function test_a_driver_can_be_installed_from_the_screen(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('admin.payment-methods.store'), ['key' => 'paystack'])
            ->assertRedirect();

        // Installing is not the same as switching on.
        $this->assertDatabaseHas('payment_methods', ['key' => 'paystack', 'is_enabled' => false]);
    }

    public function test_installing_an_unknown_driver_is_a_404(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('admin.payment-methods.store'), ['key' => 'not-a-driver'])
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function test_only_staff_with_the_permission_see_the_order_ledger(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))->get(route('admin.orders.index'))->assertOk();

        foreach ([Role::Instructor, Role::Student] as $role) {
            $this->actingAs($this->userWithRole($role->value))
                ->get(route('admin.orders.index'))
                ->assertForbidden();
        }
    }

    public function test_recording_a_refund_requires_a_reason(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $order = Order::factory()->paid()->create();

        $this->actingAs($admin)
            ->post(route('admin.orders.refund', $order), [])
            ->assertSessionHasErrors('note');

        $this->actingAs($admin)
            ->post(route('admin.orders.refund', $order), ['note' => 'Student withdrew before the course began'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertFalse($order->fresh()->status->grantsAccess());
    }

    /*
    |--------------------------------------------------------------------------
    | Coupons
    |--------------------------------------------------------------------------
    */

    public function test_every_coupon_screen_renders(): void
    {
        // Added after a Blade parse error shipped past the suite: the other tests here
        // assert redirects and database rows, so nothing ever RENDERED the list or the
        // edit form. "used@if(...)" is not a directive — Blade needs a word boundary
        // before @ — which left an orphaned @endif and 500'd both pages.
        $admin = $this->userWithRole(Role::Admin->value);
        $coupon = Coupon::factory()->create(['code' => 'RENDERME', 'max_redemptions' => 10]);

        $this->actingAs($admin)->get(route('admin.coupons.index'))->assertOk()->assertSee('RENDERME');
        $this->actingAs($admin)->get(route('admin.coupons.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.coupons.edit', $coupon))->assertOk()->assertSee('RENDERME');
    }

    public function test_the_order_screens_render(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $order = Order::factory()->paid()->create();

        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk()->assertSee($order->shortReference());
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_an_admin_can_create_a_global_code(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('admin.coupons.store'), [
                'code' => 'welcome20',
                'type' => CouponType::Percentage->value,
                'value' => 20,
                'scope' => CouponScope::Global->value,
                'per_user_limit' => 1,
                'is_active' => 1,
            ])->assertRedirect(route('admin.coupons.index'));

        // Normalised to upper case so students cannot be caught out by case.
        $this->assertDatabaseHas('coupons', ['code' => 'WELCOME20']);
    }

    public function test_an_instructor_cannot_create_a_catalogue_wide_code(): void
    {
        // A global code costs the institution money across every course.
        $this->actingAs($this->userWithRole(Role::Instructor->value))
            ->post(route('admin.coupons.store'), [
                'code' => 'SNEAKY',
                'type' => CouponType::Percentage->value,
                'value' => 50,
                'scope' => CouponScope::Global->value,
            ])->assertSessionHasErrors('scope');

        $this->assertDatabaseMissing('coupons', ['code' => 'SNEAKY']);
    }

    public function test_an_instructor_can_create_a_code_for_their_own_course(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);

        $this->actingAs($instructor)
            ->post(route('admin.coupons.store'), [
                'code' => 'MYCLASS',
                'type' => CouponType::Percentage->value,
                'value' => 10,
                'scope' => CouponScope::Course->value,
                'course_id' => $course->id,
                'per_user_limit' => 1,
                'is_active' => 1,
            ])->assertRedirect();

        $this->assertDatabaseHas('coupons', ['code' => 'MYCLASS', 'course_id' => $course->id]);
    }

    public function test_an_instructor_cannot_create_a_code_for_a_course_they_do_not_teach(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $other = Course::factory()->create();

        $this->actingAs($instructor)
            ->post(route('admin.coupons.store'), [
                'code' => 'NOTMINE',
                'type' => CouponType::Percentage->value,
                'value' => 10,
                'scope' => CouponScope::Course->value,
                'course_id' => $other->id,
            ])->assertSessionHasErrors('course_id');
    }

    public function test_a_percentage_over_one_hundred_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('admin.coupons.store'), [
                'code' => 'TOOMUCH',
                'type' => CouponType::Percentage->value,
                'value' => 150,
                'scope' => CouponScope::Global->value,
            ])->assertSessionHasErrors('value');
    }

    public function test_duplicate_codes_are_rejected_regardless_of_case(): void
    {
        Coupon::factory()->create(['code' => 'SAVE20']);

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('admin.coupons.store'), [
                'code' => 'save20',
                'type' => CouponType::Percentage->value,
                'value' => 10,
                'scope' => CouponScope::Global->value,
            ])->assertSessionHasErrors('code');
    }

    public function test_a_used_code_is_deactivated_rather_than_deleted(): void
    {
        // The redemption ledger must keep pointing at something real.
        $coupon = Coupon::factory()->create();
        $coupon->redemptions()->create([
            'user_id' => $this->userWithRole(Role::Student->value)->id,
            'order_id' => Order::factory()->paid()->create()->id,
            'discount_amount' => 500,
        ]);

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'is_active' => false]);
    }

    public function test_an_unused_code_is_deleted_outright(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->delete(route('admin.coupons.destroy', $coupon));

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_a_codes_limit_cannot_be_lowered_below_what_has_been_redeemed(): void
    {
        $coupon = Coupon::factory()->create(['max_redemptions' => 10]);

        foreach (range(1, 3) as $i) {
            $coupon->redemptions()->create([
                'user_id' => $this->userWithRole(Role::Student->value)->id,
                'order_id' => Order::factory()->paid()->create()->id,
                'discount_amount' => 100,
            ]);
        }

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->put(route('admin.coupons.update', $coupon), [
                'type' => CouponType::Percentage->value,
                'value' => 20,
                'max_redemptions' => 2,
                'per_user_limit' => 1,
            ])->assertSessionHasErrors('max_redemptions');
    }
}
