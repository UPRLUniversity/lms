<?php

namespace App\Services\Courses;

use App\Enums\AuditEvent;
use App\Models\Course;
use App\Models\CourseChange;
use App\Notifications\CourseUpdatedNotification;
use App\Support\Audit\AuditLogger;
use App\Support\Curriculum\CurriculumChange;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Records what changed in a course and, when it matters, tells the students taking it.
 *
 * Called ONCE per controller action with everything that changed, so a save that moves a
 * deadline and makes an item required produces one notification, not two. Silence is the
 * default: nothing is sent for cosmetic edits, or for a course nobody is taking yet.
 */
class CourseChangeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, CurriculumChange>  $changes
     * @return int  how many rows were written
     */
    public function record(Course $course, array $changes, ?string $note = null): int
    {
        if ($changes === []) {
            return 0;
        }

        $actorId = Auth::id();
        $material = [];

        foreach ($changes as $change) {
            CourseChange::create([
                'course_id' => $course->id,
                'user_id' => $actorId,
                'subject_type' => $change->subject?->getMorphClass(),
                'subject_id' => $change->subject?->getKey(),
                'action' => $change->action,
                'summary' => $change->summary,
                'note' => $change->isMaterial() ? $note : null,
                'significance' => $change->significance->value,
            ]);

            if ($change->isMaterial()) {
                $material[] = $change->summary;
            }
        }

        if ($material !== []) {
            $this->announce($course, $material, $note);
        }

        return \count($changes);
    }

    /**
     * Record a refused deletion. Nothing changed for students, so this is audit-only —
     * but an instructor repeatedly hitting the guard is worth an administrator seeing.
     */
    public function recordBlockedDeletion(Course $course, string $reason, ?object $subject = null): void
    {
        $this->audit->record(
            AuditEvent::CurriculumDeleteBlocked,
            $subject instanceof \Illuminate\Database\Eloquent\Model ? $subject : $course,
            ['reason' => $reason, 'course_id' => $course->id],
            $reason,
        );
    }

    /**
     * Tell everyone currently taking the course, once, and leave an audit entry.
     *
     * @param  array<int, string>  $summaries
     */
    private function announce(Course $course, array $summaries, ?string $note): void
    {
        $this->audit->record(
            AuditEvent::CourseChangedWithEnrollments,
            $course,
            ['changes' => $summaries, 'note' => $note],
            'Course changed while students were enrolled',
        );

        // Active + Completed. Completed students are told too: their grade is frozen, but
        // they may still be revisiting the material and deserve to know it moved.
        $students = $course->enrolledStudents();

        if ($students->isEmpty()) {
            return;
        }

        Notification::send(
            $students,
            new CourseUpdatedNotification($course, $summaries, $note),
        );
    }
}
