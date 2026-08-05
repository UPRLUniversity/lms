<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEvent;
use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\LessonType;
use App\Enums\PaymentEnvironment;
use App\Enums\Role;
use App\Models\AuditActivity;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 15, acceptance criterion 3: the audit viewer shows a curriculum reorder, a
 * coupon deactivation, a payment-method credential rotation (with the credential itself
 * redacted) and a programme fee change — each with a readable before/after where one
 * applies. And the log is append-only.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | The four named scenarios
    |--------------------------------------------------------------------------
    */

    public function test_a_coupon_deactivation_is_recorded_with_a_diff(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $coupon = Coupon::create([
            'code' => 'WELCOME10',
            'type' => CouponType::Percentage->value,
            'value' => 10,
            'scope' => CouponScope::Global->value,
            'per_user_limit' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);
        $coupon->update(['is_active' => false]);

        $entry = $this->latest(AuditEvent::CouponDeactivated);

        $this->assertNotNull($entry, 'Deactivating a code must be recorded as a deactivation, not a generic update.');
        $this->assertTrue((bool) $entry->before()['is_active']);
        $this->assertFalse((bool) $entry->after()['is_active']);
        $this->assertSame('commerce', $entry->log_name);
    }

    public function test_a_programme_fee_change_is_recorded_with_before_and_after(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        $programme = Programme::factory()->create(['registration_fee' => 25000]);
        $programme->update(['registration_fee' => 30000]);

        $entry = $this->latest(AuditEvent::ProgrammeFeeChanged);

        $this->assertNotNull($entry, 'A fee change must be distinguishable from any other programme edit.');
        $this->assertSame(25000.0, (float) $entry->before()['registration_fee']);
        $this->assertSame(30000.0, (float) $entry->after()['registration_fee']);
        $this->assertSame($admin->id, $entry->causer_id);
    }

    public function test_rotating_gateway_credentials_is_recorded_without_the_credential(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        $method = PaymentMethod::create([
            'key' => 'paystack',
            'label' => 'Paystack',
            'is_enabled' => false,
            'environment' => PaymentEnvironment::Test,
            'config' => ['public_key' => 'pk_test_old', 'secret_key' => 'sk_test_OLD_SECRET'],
            'position' => 1,
        ]);

        $method->update(['config' => ['public_key' => 'pk_live_new', 'secret_key' => 'sk_live_NEW_SECRET']]);

        $entry = $this->latest(AuditEvent::PaymentMethodCredentialsRotated);
        $this->assertNotNull($entry, 'A credential rotation must be recorded.');

        // The whole point: the log shows THAT the credentials changed, never their value.
        $this->assertTrue($entry->isRedacted('config'));
        $this->assertSame(AuditLogger::REDACTED, $entry->after()['config']);

        $serialised = json_encode($entry->properties);
        $this->assertStringNotContainsString('sk_test_OLD_SECRET', $serialised);
        $this->assertStringNotContainsString('sk_live_NEW_SECRET', $serialised);
        $this->assertStringNotContainsString('pk_live_new', $serialised);

        // Nowhere in the whole table, not merely in this row.
        foreach (AuditActivity::all() as $row) {
            $this->assertStringNotContainsString('sk_live_NEW_SECRET', json_encode($row->properties));
        }
    }

    public function test_saving_a_payment_method_without_changing_the_secret_is_not_a_rotation(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        $method = PaymentMethod::create([
            'key' => 'paystack',
            'label' => 'Paystack',
            'is_enabled' => false,
            'environment' => PaymentEnvironment::Test,
            'config' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1'],
            'position' => 1,
        ]);

        AuditActivity::query()->getQuery()->delete();

        // An encrypted column produces fresh ciphertext on every write. If dirtiness
        // were judged on the ciphertext, merely renaming the method would log a
        // credential rotation that never happened — a false alarm in exactly the log
        // a security review trusts most.
        $method->update([
            'label' => 'Paystack (Nigeria)',
            'config' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1'],
        ]);

        $this->assertNull(
            $this->latest(AuditEvent::PaymentMethodCredentialsRotated),
            'Re-saving identical credentials must not be reported as a rotation.',
        );
        $this->assertNotNull($this->latest(AuditEvent::PaymentMethodUpdated));
    }

    public function test_a_curriculum_reorder_is_recorded(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->create(['created_by' => $instructor->id]);
        $course->instructors()->attach($instructor->id, ['is_lead' => true]);

        $moduleA = $course->modules()->create(['title' => 'Module A', 'position' => 1]);
        $moduleB = $course->modules()->create(['title' => 'Module B', 'position' => 2]);

        $lesson = $moduleA->lessons()->create([
            'title' => 'Opening lesson',
            'type' => LessonType::Text->value,
            'position' => 1,
        ]);

        $this->actingAs($instructor)
            ->postJson(route('courses.curriculum.reorder', $course), [
                'order' => [
                    ['module_id' => $moduleA->id, 'items' => []],
                    ['module_id' => $moduleB->id, 'items' => [['type' => 'lesson', 'id' => $lesson->id]]],
                ],
            ])
            ->assertOk();

        $entry = $this->latest(AuditEvent::CurriculumReordered);

        $this->assertNotNull($entry, 'Moving curriculum items must be recorded — it changes what a student must do.');
        $this->assertSame($course->id, $entry->subject_id);
        $this->assertSame($instructor->id, $entry->causer_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Append-only
    |--------------------------------------------------------------------------
    */

    public function test_entries_cannot_be_edited_or_deleted(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        Programme::factory()->create();
        $entry = AuditActivity::latest('id')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $entry->update(['description' => 'something else']);
    }

    public function test_entries_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        Programme::factory()->create();
        $entry = AuditActivity::latest('id')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $entry->delete();
    }

    public function test_there_is_no_route_that_mutates_the_audit_log(): void
    {
        $mutating = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'admin/audit'))
            ->reject(fn ($route) => array_intersect($route->methods(), ['GET', 'HEAD']) !== []);

        $this->assertCount(0, $mutating, 'The audit area must expose no mutating route.');
    }

    /*
    |--------------------------------------------------------------------------
    | The viewer
    |--------------------------------------------------------------------------
    */

    public function test_viewer_is_reachable_by_admin_and_auditor_but_not_a_student(): void
    {
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->get(route('admin.audit.index'))->assertOk();

        // The read-only observer's whole purpose.
        $this->actingAs($this->userWithRole(Role::Auditor->value))
            ->get(route('admin.audit.index'))->assertOk();

        $this->actingAs($this->userWithRole(Role::Student->value))
            ->get(route('admin.audit.index'))->assertForbidden();

        $this->actingAs($this->userWithRole(Role::Instructor->value))
            ->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_viewer_filters_by_event_and_actor(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        $programme = Programme::factory()->create(['name' => 'Fee Change Programme', 'registration_fee' => 1000]);
        $programme->update(['registration_fee' => 2000]);

        $this->get(route('admin.audit.index', ['event' => AuditEvent::ProgrammeFeeChanged->value]))
            ->assertOk()
            ->assertSee('Fee Change Programme');

        // A filter that matches nothing shows the empty state rather than everything.
        $this->get(route('admin.audit.index', ['event' => AuditEvent::CertificateRevoked->value]))
            ->assertOk()
            ->assertSee('Nothing matches those filters');
    }

    public function test_csv_export_streams_the_filtered_set_and_redacts_secrets(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin);

        PaymentMethod::create([
            'key' => 'paystack',
            'label' => 'Paystack',
            'is_enabled' => false,
            'environment' => PaymentEnvironment::Test,
            'config' => ['secret_key' => 'sk_live_MUST_NOT_APPEAR'],
            'position' => 1,
        ])->update(['config' => ['secret_key' => 'sk_live_ALSO_MUST_NOT_APPEAR']]);

        $response = $this->get(route('admin.audit.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Recorded at', $csv);
        $this->assertStringContainsString('Rotated gateway credentials', $csv);
        $this->assertStringNotContainsString('sk_live_MUST_NOT_APPEAR', $csv);
        $this->assertStringNotContainsString('sk_live_ALSO_MUST_NOT_APPEAR', $csv);
    }

    public function test_a_failed_sign_in_records_its_origin_without_the_password(): void
    {
        $user = $this->userWithRole(Role::Student->value, ['email' => 'target@uprl.test']);

        $this->post(route('login'), [
            'email' => 'target@uprl.test',
            'password' => 'the-wrong-password',
        ]);

        $entry = $this->latest(AuditEvent::LoginFailed);

        $this->assertNotNull($entry, 'A failed sign-in must be recorded.');
        $this->assertSame('target@uprl.test', $entry->context()['attempted_email']);
        $this->assertTrue($entry->context()['account_exists']);
        $this->assertArrayHasKey('ip', $entry->context());

        // The attempted password is never stored, in any form.
        $this->assertStringNotContainsString('the-wrong-password', json_encode($entry->properties));
    }

    public function test_a_successful_sign_in_is_recorded(): void
    {
        $user = $this->userWithRole(Role::Student->value, ['email' => 'learner@uprl.test']);

        $this->post(route('login'), [
            'email' => 'learner@uprl.test',
            'password' => 'password',
        ]);

        $entry = $this->latest(AuditEvent::LoginSucceeded);

        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->causer_id);
    }

    private function latest(AuditEvent $event): ?AuditActivity
    {
        return AuditActivity::where('event', $event->value)->latest('id')->first();
    }
}
