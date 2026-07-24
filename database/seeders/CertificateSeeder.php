<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\Certificates\CertificateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Runs LAST (after every other seeder, so every genuinely-completed enrolment has
 * already had its chance to auto-issue a certificate through the real CourseCompleted
 * pipeline — see CertificateTemplateSeeder's note on why templates are seeded early).
 * This step only (a) tops up the registry if the natural pipeline produced fewer than
 * three, and (b) revokes the earliest-issued one so the demo shows every state.
 */
class CertificateSeeder extends Seeder
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function run(): void
    {
        $this->topUp();

        $toRevoke = Certificate::query()->whereNull('revoked_at')->oldest('id')->first();

        if ($toRevoke && $toRevoke->revocation_reason === null) {
            $this->certificates->revoke(
                $toRevoke,
                'Academic integrity review — certificate withdrawn pending investigation.'
            );
        }
    }

    /**
     * Manually issue for any already-completed enrolment still missing a certificate,
     * until the registry has at least three — a richer, more convincing demo table.
     */
    private function topUp(): void
    {
        if (Certificate::count() >= 3) {
            return;
        }

        $missing = Enrollment::query()
            ->where('status', EnrollmentStatus::Completed->value)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('certificates')
                    ->whereColumn('certificates.user_id', 'enrollments.user_id')
                    ->whereColumn('certificates.course_id', 'enrollments.course_id');
            })
            ->with(['user', 'course'])
            ->oldest('completed_at')
            ->get();

        foreach ($missing as $enrollment) {
            if (Certificate::count() >= 3) {
                break;
            }

            $this->certificates->issueManually($enrollment->user, $enrollment->course);
        }
    }
}
