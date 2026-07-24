<?php

namespace App\Services\Grades;

use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes the one-time completion snapshot (Enrollment → Completed, via the
 * CourseCompleted event) and the explicit admin recompute path. Both funnel through
 * here so "at most one CURRENT record per user+course" and "old versions are
 * superseded, never edited" hold everywhere they're written.
 */
class CourseGradeRecordService
{
    public function __construct(private readonly GradebookService $gradebook) {}

    /**
     * Idempotent: a second call for an already-recorded completion (e.g. a re-fired
     * completion event after un-marking then re-completing a lesson) is a silent
     * no-op — it returns the existing record rather than duplicating or touching it.
     */
    public function recordCompletion(User $user, Course $course): ?CourseGradeRecord
    {
        return DB::transaction(function () use ($user, $course) {
            $existing = CourseGradeRecord::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->current()
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $this->write($user, $course, version: 1);
        });
    }

    /**
     * Explicit admin re-issue: supersedes the current record with a freshly computed
     * new version against the course's CURRENT scale. Never edits the old row, so a
     * prior recorded grade remains inspectable in history.
     */
    public function recompute(Course $course, User $student): ?CourseGradeRecord
    {
        return DB::transaction(function () use ($course, $student) {
            $current = CourseGradeRecord::query()
                ->where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->current()
                ->lockForUpdate()
                ->first();

            $next = $this->write($student, $course, version: ($current?->version ?? 0) + 1);

            if ($next !== null) {
                $current?->update(['superseded_at' => now()]);
            }

            return $next;
        });
    }

    private function write(User $user, Course $course, int $version): ?CourseGradeRecord
    {
        $scale = $course->gradeScaleOrDefault();
        if ($scale === null) {
            return null;
        }

        $summary = $this->gradebook->summaryFor($user, $course, $scale);
        if (! $summary->isFinal()) {
            return null;
        }

        return CourseGradeRecord::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'version' => $version,
            'final_percent' => $summary->percent,
            'grade_label' => $summary->gradeLabel(),
            'grade_point' => $summary->gradePoint(),
            'scale_snapshot' => $scale->toSnapshot(),
            'computed_at' => now(),
        ]);
    }
}
