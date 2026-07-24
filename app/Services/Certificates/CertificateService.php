<?php

namespace App\Services\Certificates;

use App\Enums\MediaPurpose;
use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\User;
use App\Services\Grades\CourseGradeRecordService;
use App\Services\Media\PrivateFileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issues, re-issues and revokes certificates. One row per (user, course) — a
 * re-completion never duplicates (see issueForCompletion). The heavy PDF render always
 * happens in the queued GenerateCertificatePdf job; this service only ever writes the
 * fast metadata row (serial, snapshot) and dispatches the job.
 */
class CertificateService
{
    public function __construct(
        private readonly CourseGradeRecordService $records,
        private readonly PrivateFileService $files,
    ) {}

    /**
     * Called from the CourseCompleted pipeline. Defensively ensures the Section 6.5
     * grade snapshot exists first — listener order across the two Section 7/6.5
     * listeners on the same event isn't guaranteed, and recordCompletion() is itself
     * idempotent, so this is a safe no-op when it's already been written.
     */
    public function issueForCompletion(User $user, Course $course): Certificate
    {
        $existing = $this->existing($user, $course);
        if ($existing !== null) {
            return $existing;
        }

        $this->records->recordCompletion($user, $course);

        return $this->issue($user, $course);
    }

    /**
     * Admin "Issue" action for a completed student who has no certificate yet.
     * Idempotent for the same reason as issueForCompletion.
     */
    public function issueManually(User $user, Course $course): Certificate
    {
        return $this->existing($user, $course) ?? $this->issue($user, $course);
    }

    /**
     * Admin "Re-issue": re-freezes the snapshot against the course's CURRENT template
     * and grade record, and re-renders the PDF. The serial and public_id never change —
     * it's the same certificate, already possibly distributed/scanned.
     */
    public function reissue(Certificate $certificate): Certificate
    {
        $template = $this->resolveTemplate($certificate->course);

        $certificate->forceFill([
            'certificate_template_id' => $template->id,
            'snapshot' => $this->buildSnapshot($certificate->user, $certificate->course, $template),
            'issued_at' => now(),
            'rendered_at' => null,
        ])->save();

        $this->clearPdf($certificate);

        GenerateCertificatePdf::dispatch($certificate->id);

        return $certificate;
    }

    public function revoke(Certificate $certificate, string $reason): Certificate
    {
        $certificate->update(['revoked_at' => now(), 'revocation_reason' => $reason]);

        return $certificate;
    }

    public function restore(Certificate $certificate): Certificate
    {
        $certificate->update(['revoked_at' => null, 'revocation_reason' => null]);

        return $certificate;
    }

    private function existing(User $user, Course $course): ?Certificate
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }

    private function issue(User $user, Course $course): Certificate
    {
        return DB::transaction(function () use ($user, $course) {
            // Re-check inside the transaction: a race between two triggers (e.g. the
            // completion listener and a concurrent admin "Issue") can't create two rows —
            // the (user_id, course_id) unique index is the final backstop either way.
            $existing = Certificate::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $template = $this->resolveTemplate($course);

            $certificate = Certificate::create([
                'public_id' => (string) Str::ulid(),
                'serial' => $this->generateSerial(),
                'user_id' => $user->id,
                'course_id' => $course->id,
                'certificate_template_id' => $template->id,
                'snapshot' => $this->buildSnapshot($user, $course, $template),
                'issued_at' => now(),
            ]);

            GenerateCertificatePdf::dispatch($certificate->id);

            return $certificate;
        });
    }

    private function resolveTemplate(Course $course): CertificateTemplate
    {
        $template = $course->certificateTemplateOrDefault();

        if ($template === null) {
            throw new RuntimeException('No certificate template is configured — seed a default one first.');
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(User $user, Course $course, CertificateTemplate $template): array
    {
        $snapshot = $template->toSnapshot();

        $enrollment = $course->enrollmentFor($user);

        $snapshot['student_name'] = $user->name;
        $snapshot['course_title'] = $course->title;
        $snapshot['completion_date'] = ($enrollment?->completed_at ?? now())->isoFormat('D MMMM YYYY');
        $snapshot['grade'] = null;

        if ($snapshot['show_grade']) {
            $record = CourseGradeRecord::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->current()
                ->first();

            if ($record !== null) {
                $scaleSnapshot = $record->scale_snapshot;

                $snapshot['grade'] = [
                    'final_percent' => (float) $record->final_percent,
                    'grade_label' => $record->grade_label,
                    'grade_point' => (float) $record->grade_point,
                    'scale_name' => $scaleSnapshot['name'] ?? null,
                    'display_mode' => $scaleSnapshot['display_mode'] ?? 'both',
                    'separator' => $scaleSnapshot['separator'] ?? '/',
                    'show_scale_limit' => $scaleSnapshot['show_scale_limit'] ?? true,
                    'scale_limit' => $scaleSnapshot['scale_limit'] ?? 0,
                ];
            }
        }

        return $snapshot;
    }

    private function generateSerial(): string
    {
        do {
            $serial = 'UPRL-'.now()->year.'-'.Str::upper(Str::random(6));
        } while (Certificate::query()->where('serial', $serial)->exists());

        return $serial;
    }

    private function clearPdf(Certificate $certificate): void
    {
        $certificate->mediaFor(MediaPurpose::Certificates)->each(
            fn ($media) => $this->files->delete($media)
        );
    }
}
