<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Services\Certificates\CertificateService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues (never duplicates) a certificate the moment an enrollment reaches Completed —
 * the same CourseCompleted event Section 6.5's RecordCourseGradeSnapshot listens on.
 * Listener execution order across auto-discovered listeners for one event isn't
 * guaranteed, so CertificateService::issueForCompletion defensively ensures the grade
 * snapshot exists itself rather than assuming the other listener already ran.
 *
 * Runs synchronously, inline in the request that just completed the course — so any
 * failure here (e.g. no template configured yet) is caught and logged rather than
 * bubbling up: a certificate problem must never turn a student's "lesson complete"
 * request into a 500.
 */
class IssueCertificateOnCompletion
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function handle(CourseCompleted $event): void
    {
        try {
            $this->certificates->issueForCompletion($event->user, $event->course);
        } catch (Throwable $e) {
            Log::error('Certificate issuance failed on course completion', [
                'user_id' => $event->user->id,
                'course_id' => $event->course->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
