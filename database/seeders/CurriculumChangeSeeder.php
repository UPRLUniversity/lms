<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseChange;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Courses\LearningService;
use App\Support\Curriculum\ChangeSignificance;
use Illuminate\Database\Seeder;

/**
 * Section 16 — makes the three new surfaces non-empty on a fresh seed: the builder's
 * change history, the learner's "What's changed" panel, and a hidden curriculum item
 * that still holds student work.
 *
 * Runs last, over courses that already have real enrolments and progress, so every row
 * here describes something that genuinely exists rather than inventing a parallel course.
 */
class CurriculumChangeSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()
            ->whereHas('enrollments', fn ($q) => $q->where('status', EnrollmentStatus::Active->value))
            ->whereHas('modules.lessons')
            ->with(['modules.lessons'])
            ->first();

        if ($course === null) {
            return;
        }

        $instructor = $course->instructors()->first() ?? User::query()->find($course->created_by);

        $this->hideALessonStudentsHaveDone($course, $instructor);
        $this->recordAPlausibleHistory($course, $instructor);
        $this->freezeOneCompletion($course);
    }

    /**
     * A lesson withdrawn from the course whose progress rows survive — the exact situation
     * the delete guard exists to produce, so the demo shows the outcome rather than only
     * the refusal.
     */
    private function hideALessonStudentsHaveDone(Course $course, ?User $instructor): void
    {
        $lesson = $course->modules
            ->flatMap->lessons
            ->first(fn (Lesson $l) => $l->progress()->exists());

        if ($lesson === null || $lesson->isHidden()) {
            return;
        }

        $lesson->hide();

        CourseChange::create([
            'course_id' => $course->id,
            'user_id' => $instructor?->id,
            'subject_type' => $lesson->getMorphClass(),
            'subject_id' => $lesson->id,
            'action' => 'hidden',
            'significance' => ChangeSignificance::Material->value,
            'summary' => "“{$lesson->title}” has been withdrawn from the course.",
            'note' => 'Superseded by the updated reading — your progress on it is kept.',
            'created_at' => now()->subDays(2),
        ]);
    }

    /**
     * A short, believable history so the builder tab and the learner panel both read like
     * a course somebody has actually been maintaining.
     */
    private function recordAPlausibleHistory(Course $course, ?User $instructor): void
    {
        if (CourseChange::query()->where('course_id', $course->id)->count() > 1) {
            return;
        }

        $assignment = $course->assignments()->first();
        $entries = [];

        if ($assignment !== null) {
            $entries[] = [
                'action' => 'updated',
                'significance' => ChangeSignificance::Material,
                'summary' => "“{$assignment->title}”: due date changed from 12 Aug 2026, 11:59pm to 19 Aug 2026, 11:59pm.",
                'note' => 'Extended by a week after the public holiday.',
                'days' => 5,
                'subject' => $assignment,
            ];
            $entries[] = [
                'action' => 'updated',
                'significance' => ChangeSignificance::Cosmetic,
                'summary' => "“{$assignment->title}”: instructions updated.",
                'note' => null,
                'days' => 4,
                'subject' => $assignment,
            ];
        }

        $entries[] = [
            'action' => 'reordered',
            'significance' => ChangeSignificance::Material,
            'summary' => 'The course order changed, so what unlocks next may have moved.',
            'note' => null,
            'days' => 8,
            'subject' => null,
        ];

        foreach ($entries as $entry) {
            /** @var \Illuminate\Database\Eloquent\Model|null $subject */
            $subject = $entry['subject'];

            CourseChange::create([
                'course_id' => $course->id,
                'user_id' => $instructor?->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'action' => $entry['action'],
                'significance' => $entry['significance']->value,
                'summary' => $entry['summary'],
                'note' => $entry['note'],
                'created_at' => now()->subDays($entry['days']),
            ]);
        }
    }

    /**
     * One completed enrolment carrying a real snapshot, so the frozen-grade behaviour is
     * inspectable in the demo rather than only in the tests.
     */
    private function freezeOneCompletion(Course $course): void
    {
        $enrollment = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('status', EnrollmentStatus::Completed->value)
            ->whereNull('completion_snapshot')
            ->first();

        if ($enrollment === null) {
            return;
        }

        $enrollment->forceFill([
            'completion_snapshot' => app(LearningService::class)->completionSnapshot($course)->toArray(),
            'completion_snapshot_at' => $enrollment->completed_at ?? now(),
        ])->save();
    }
}
