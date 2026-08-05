<?php

namespace Database\Seeders;

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Enums\SettingGroup;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\GradeScale;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\User;
use App\Services\Courses\CurriculumOrderService;
use App\Services\Settings\SettingsUpdateService;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Facades\Activity;

/**
 * A believable audit trail for the demo (Section 15).
 *
 * The rest of DatabaseSeeder runs inside Activity::withoutLogs(), because seeding tens
 * of thousands of rows would otherwise bury the trail in machine noise and make the
 * viewer useless to click through. This seeder then produces a small, deliberate
 * history by performing REAL actions through the real services — not by inserting
 * activity_log rows directly.
 *
 * That distinction matters: entries written by hand would prove nothing about whether
 * the instrumentation works. These go through the same code paths a human would, so if
 * the seeded trail looks right, the trail genuinely works.
 *
 * Every scenario named in the Section 15 acceptance criteria appears here:
 * a curriculum reorder, a coupon deactivation, a payment-method credential rotation
 * (with the value redacted) and a programme fee change.
 */
class AuditTrailSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role(Role::Admin->value)->first();
        $superAdmin = User::role(Role::SuperAdmin->value)->first();
        $instructor = User::role(Role::Instructor->value)->first();

        if (! $admin || ! $superAdmin) {
            return;
        }

        $this->asUser($admin, function () {
            $this->programmeFeeChange();
            $this->couponDeactivation();
            $this->credentialRotation();
        });

        if ($instructor) {
            $this->asUser($instructor, fn () => $this->curriculumReorder());
        }

        $this->asUser($superAdmin, function () {
            $this->settingsChange();
            $this->defaultScaleChange();
        });

        // A failed sign-in, so the "who is being probed" view is not empty. Recorded
        // through the same logger the auth listener uses.
        $this->failedSignIn();
    }

    /**
     * Perform a block of work AS a given user, so the audit entries carry a real causer.
     */
    private function asUser(User $user, callable $work): void
    {
        $previous = Auth::user();

        // The sign-in itself is suppressed: the seeder authenticating in order to set a
        // causer is plumbing, not activity, and six "Signed in / Signed out" pairs would
        // crowd out the six entries this seeder exists to produce. A real failed
        // sign-in is recorded separately below so the auth view is not empty.
        Activity::withoutLogs(fn () => Auth::login($user));

        try {
            $work();
        } finally {
            Activity::withoutLogs(fn () => $previous ? Auth::login($previous) : Auth::logout());
        }
    }

    /** Fees decide what a student is charged — the change an auditor looks for first. */
    private function programmeFeeChange(): void
    {
        $programme = Programme::query()->orderBy('position')->first();

        if ($programme && (float) $programme->registration_fee > 0) {
            $programme->update([
                'registration_fee' => round((float) $programme->registration_fee * 1.10, 2),
            ]);
        }
    }

    private function couponDeactivation(): void
    {
        $coupon = Coupon::query()->where('is_active', true)->first();

        $coupon?->update(['is_active' => false]);
    }

    /**
     * Rotating a gateway secret. The log records THAT it changed; the value never
     * reaches the trail — see AuditLogger's redaction.
     */
    private function credentialRotation(): void
    {
        $method = PaymentMethod::query()->where('key', 'paystack')->first();

        $method?->update([
            'config' => [
                'public_key' => 'pk_test_rotated_'.substr(md5((string) now()), 0, 8),
                'secret_key' => 'sk_test_rotated_'.substr(md5((string) now().'s'), 0, 12),
            ],
        ]);
    }

    /**
     * A real reorder through CurriculumOrderService: the last module's items are moved
     * ahead of the first module's, which is exactly the kind of change that alters what
     * a student has to work through.
     */
    private function curriculumReorder(): void
    {
        $course = Course::query()
            ->whereHas('modules')
            ->with('modules.lessons')
            ->first();

        if (! $course || $course->modules->count() < 2) {
            return;
        }

        $order = $course->modules
            ->sortByDesc('position')      // reverse the module order
            ->values()
            ->map(fn ($module) => [
                'module_id' => $module->id,
                'items' => $module->lessons
                    ->sortBy('position')
                    ->values()
                    ->map(fn ($lesson) => ['type' => 'lesson', 'id' => $lesson->id])
                    ->all(),
            ])
            ->all();

        app(CurriculumOrderService::class)->apply($course, $order);
    }

    private function settingsChange(): void
    {
        app(SettingsUpdateService::class)->apply(
            SettingGroup::General,
            ['general.date_format' => 'j F Y'],
        );
    }

    /**
     * Promote the non-default scale, so the viewer shows a readable before/after on the
     * setting that changes how every un-overridden course displays its grades.
     */
    private function defaultScaleChange(): void
    {
        $target = GradeScale::query()->where('is_default', false)->first();

        if ($target) {
            app(SettingsUpdateService::class)->apply(
                SettingGroup::Grading,
                ['grading.default_scale_id' => $target->id],
            );
        }
    }

    /**
     * A failed sign-in against a real account, from a plausible address — so the
     * "failing origin" the section calls for is demonstrable without anyone having to
     * mistype a password to see it.
     */
    private function failedSignIn(): void
    {
        $target = User::role(Role::Student->value)->first();

        if (! $target) {
            return;
        }

        app(AuditLogger::class)->record(
            AuditEvent::LoginFailed,
            $target,
            [
                'ip' => '102.89.44.17',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'attempted_email' => $target->email,
                'account_exists' => true,
            ],
            'Failed sign-in attempt',
        );
    }
}
