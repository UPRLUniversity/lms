<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Enums\SettingGroup;
use App\Models\AuditActivity;
use App\Models\Course;
use App\Models\GradeScale;
use App\Providers\SettingsServiceProvider;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 15 — /admin/settings: access, persistence, the config override that makes a
 * saved setting reach code reading config(), and the audit entry every save leaves.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_screen_is_super_admin_only(): void
    {
        // An ordinary admin manages people and courses, but not institution-wide
        // settings — that boundary is the point of the separate permission.
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertForbidden();

        $auditor = $this->userWithRole(Role::Auditor->value);
        $this->actingAs($auditor)->get(route('admin.settings.index'))->assertForbidden();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $this->actingAs($instructor)->get(route('admin.settings.index'))->assertForbidden();

        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);
        $this->actingAs($superAdmin)->get(route('admin.settings.index'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('login'));
    }

    public function test_every_tab_renders(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        foreach (SettingGroup::cases() as $group) {
            $this->actingAs($superAdmin)
                ->get(route('admin.settings.index', $group->value))
                ->assertOk()
                ->assertSee($group->label());
        }
    }

    public function test_saving_a_setting_persists_and_overrides_config(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'group' => 'general',
                'settings' => $this->generalPayload(['general__university_name' => 'Test Institute of Leadership']),
            ])
            ->assertRedirect(route('admin.settings.index', 'general'));

        $this->assertDatabaseHas('settings', [
            'key' => 'general.university_name',
            'value' => 'Test Institute of Leadership',
        ]);

        // The whole mechanism: on the next boot, the stored value must arrive through
        // config(), which is what every existing call site already reads.
        //
        // The provider is re-booted rather than the application refreshed:
        // refreshApplication() drops the connection and takes RefreshDatabase's
        // open transaction — and therefore the row just saved — with it. Re-running
        // boot() exercises exactly the code a real request would.
        app(SettingsRepository::class)->flush();
        (new SettingsServiceProvider($this->app))->boot();

        $this->assertSame('Test Institute of Leadership', config('brand.university'));
        $this->assertSame('Test Institute of Leadership', setting('general.university_name'));
    }

    public function test_unchanged_values_are_not_written(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        // Post the current values back untouched.
        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'group' => 'general',
                'settings' => $this->generalPayload(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('settings', 0);
        $this->assertSame(0, AuditActivity::where('event', AuditEvent::SettingsUpdated->value)->count());
    }

    public function test_a_boolean_can_be_switched_off(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        // force_email_verification defaults to true; the unticked checkbox is absent
        // from a real browser payload, which is the case that must still turn it off.
        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'group' => 'security',
                'settings' => [
                    'security__password_min_length' => 8,
                    'security__session_lifetime' => 120,
                ],
            ])
            ->assertRedirect();

        app(SettingsRepository::class)->flush();
        $this->assertFalse(app(SettingsRepository::class)->bool('security.force_email_verification'));
    }

    public function test_invalid_input_is_rejected_with_a_field_error(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'group' => 'general',
                'settings' => $this->generalPayload(['general__support_email' => 'not-an-email']),
            ])
            ->assertSessionHasErrors('settings.general__support_email');

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_saving_one_tab_does_not_disturb_another(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'commerce',
            'settings' => [
                'commerce__currency' => 'GBP',
                'commerce__currency_symbol' => '£',
                'commerce__money_locale' => 'en_GB',
                'commerce__invoice_footer' => 'Thanks.',
                'commerce__guest_checkout_enabled' => '1',
            ],
        ])->assertRedirect();

        // Now save General. Commerce must survive untouched.
        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'general',
            'settings' => $this->generalPayload(['general__motto' => 'Onwards']),
        ])->assertRedirect();

        app(SettingsRepository::class)->flush();
        $this->assertSame('GBP', setting('commerce.currency'));
        $this->assertSame('Onwards', setting('general.motto'));
    }

    public function test_a_settings_change_is_audited_with_a_diff(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'general',
            'settings' => $this->generalPayload(['general__motto' => 'Courage, Clarity, Craft']),
        ])->assertRedirect();

        $entry = AuditActivity::where('event', AuditEvent::SettingsUpdated->value)->latest('id')->first();

        $this->assertNotNull($entry, 'A settings save must leave an audit entry.');
        $this->assertSame($superAdmin->id, $entry->causer_id);
        $this->assertSame('Creativity, Competence, Character', $entry->before()['general.motto']);
        $this->assertSame('Courage, Clarity, Craft', $entry->after()['general.motto']);

        // Only what moved is recorded — not every field on the tab.
        $this->assertSame(['general.motto'], array_keys($entry->after()));
    }

    public function test_changing_the_default_grade_scale_moves_it_and_is_audited(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $current = GradeScale::query()->where('is_default', true)->firstOrFail();
        $target = GradeScale::query()->where('id', '!=', $current->id)->firstOrFail();

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'grading',
            'settings' => ['grading__default_scale_id' => $target->id],
        ])->assertRedirect();

        // Exactly one default, and it is the chosen scale.
        $this->assertTrue($target->fresh()->is_default);
        $this->assertFalse($current->fresh()->is_default);
        $this->assertSame(1, GradeScale::where('is_default', true)->count());

        // The derived setting is NOT mirrored into the settings table — GradeScale
        // remains the single owner of that state.
        $this->assertDatabaseMissing('settings', ['key' => 'grading.default_scale_id']);

        $entry = AuditActivity::where('event', AuditEvent::DefaultScaleChanged->value)->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertSame($current->name, $entry->before()['default_grade_scale']);
        $this->assertSame($target->name, $entry->after()['default_grade_scale']);
    }

    public function test_default_grade_scale_governs_a_course_without_an_override(): void
    {
        $this->seed(GradeScaleSeeder::class);
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $target = GradeScale::query()->where('is_default', false)->firstOrFail();

        $course = Course::factory()->create(['grade_scale_id' => null]);

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), [
            'group' => 'grading',
            'settings' => ['grading__default_scale_id' => $target->id],
        ])->assertRedirect();

        // The acceptance criterion: a course with no scale of its own now follows
        // the newly chosen default.
        $this->assertSame($target->id, $course->fresh()->gradeScaleOrDefault()?->id);
    }

    /**
     * A complete, valid General tab payload — the shape a browser actually posts, so
     * a test that changes one field is not silently exercising a half-empty form.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function generalPayload(array $overrides = []): array
    {
        return array_merge([
            'general__university_name' => 'University of Public Relations and Leadership',
            'general__app_name' => 'UPRL LMS',
            'general__short_name' => 'UPRL',
            'general__motto' => 'Creativity, Competence, Character',
            // The CURRENT value, so a test changing one other field produces a
            // one-field diff rather than accidentally moving this one too.
            'general__support_email' => config('mail.support'),
            'general__timezone' => 'Africa/Lagos',
            'general__date_format' => 'd M Y',
            'general__locale' => 'en',
            'general__locale_switcher_enabled' => '0',
        ], $overrides);
    }
}
