<?php

namespace Database\Seeders;

use App\Enums\AssessmentStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeDisplayMode;
use App\Enums\GradeScaleStatus;
use App\Enums\SubmissionStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeScale;
use App\Models\LessonProgress;
use App\Models\Submission;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Database\Seeder;

/**
 * Section 6.5 demo: the two mandated scales (NUC Standard as system default, 4.0 Scale
 * as the alternative) plus a believable gradebook on the same published course the
 * assessment/assignment demos use — one student who genuinely completes the course
 * (exercising the real completion → CourseGradeRecord pipeline) and one left with a
 * pending item (a visible "Provisional" summary). Idempotent: resets its own slice of
 * demo data (two spare students' attempts/submissions on this course) each run.
 */
class GradeScaleSeeder extends Seeder
{
    public function run(): void
    {
        $nuc = $this->upsertScale('NUC Standard (5.0)', 5.00, true, [
            ['label' => 'A', 'grade_point' => 5.00, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 4.00, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
            ['label' => 'C', 'grade_point' => 3.00, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
            ['label' => 'D', 'grade_point' => 2.00, 'min_percent' => 45, 'max_percent' => 49, 'color' => 'neutral'],
            ['label' => 'E', 'grade_point' => 1.00, 'min_percent' => 40, 'max_percent' => 44, 'color' => 'neutral'],
            ['label' => 'F', 'grade_point' => 0.00, 'min_percent' => 0, 'max_percent' => 39, 'color' => 'crimson'],
        ]);

        $this->upsertScale('4.0 Scale', 4.00, false, [
            ['label' => 'A', 'grade_point' => 4.00, 'min_percent' => 80, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 3.00, 'min_percent' => 70, 'max_percent' => 79, 'color' => 'gold'],
            ['label' => 'C', 'grade_point' => 2.00, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'ink'],
            ['label' => 'D', 'grade_point' => 1.00, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'neutral'],
            ['label' => 'F', 'grade_point' => 0.00, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
        ]);

        // Only ever one default.
        GradeScale::query()->where('id', '!=', $nuc->id)->update(['is_default' => false]);

        $this->seedCourseGradebook();
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private function upsertScale(string $name, float $limit, bool $default, array $bands): GradeScale
    {
        $scale = GradeScale::updateOrCreate(['name' => $name], [
            'scale_limit' => $limit,
            'is_default' => $default,
            'display_mode' => GradeDisplayMode::Both->value,
            'show_scale_limit' => true,
            'separator' => '/',
            'status' => GradeScaleStatus::Active->value,
        ]);

        $scale->bands()->delete();
        foreach (array_values($bands) as $position => $band) {
            $scale->bands()->create($band + ['position' => $position]);
        }

        return $scale;
    }

    private function seedCourseGradebook(): void
    {
        $course = Course::query()->published()->has('modules')
            ->with(['modules.lessons', 'assessments', 'assignments'])
            ->first();

        if (! $course) {
            return;
        }

        $requiredAssessments = $course->assessments
            ->where('status', AssessmentStatus::Published)
            ->where('is_required', true)
            ->values();
        $requiredAssignment = $course->assignments
            ->where('status', AssignmentStatus::Published)
            ->where('is_required', true)
            ->first();

        if ($requiredAssessments->isEmpty() && ! $requiredAssignment) {
            return;
        }

        // Two students not already enrolled in this course, so this demo never collides
        // with the assessment/assignment seeders' own cast of students.
        $enrolledIds = $course->enrollments()->pluck('user_id');
        $spares = User::role('student')->whereNotIn('id', $enrolledIds)->orderBy('id')->take(2)->get();
        if ($spares->count() < 2) {
            return;
        }
        [$completer, $provisional] = [$spares[0], $spares[1]];

        $instructor = $course->creator ?? $course->instructors()->first() ?? User::role('instructor')->first();
        $learning = app(LearningService::class);
        $lessons = $learning->sequence($course);
        $assessmentIds = $requiredAssessments->pluck('id');

        // Idempotent reset of this seeder's own slice of data for both demo students.
        foreach ([$completer, $provisional] as $student) {
            Attempt::where('user_id', $student->id)->whereIn('assessment_id', $assessmentIds)->delete();
            if ($requiredAssignment) {
                Submission::where('user_id', $student->id)->where('assignment_id', $requiredAssignment->id)->delete();
            }
            CourseGradeRecord::where('user_id', $student->id)->where('course_id', $course->id)->delete();
            LessonProgress::where('user_id', $student->id)->whereIn('lesson_id', $lessons->pluck('id'))->delete();
            Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->delete();
        }

        // ---- The completer: passes every required item AND every lesson, so the real
        // completion pipeline fires and writes a genuine CourseGradeRecord.
        Enrollment::create([
            'user_id' => $completer->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            'source' => EnrollmentSource::Self->value,
            'enrolled_at' => now()->subDays(30),
            'progress_percent' => 0,
        ]);

        foreach ($lessons as $lesson) {
            $learning->markComplete($completer, $lesson);
        }

        foreach ($requiredAssessments as $assessment) {
            $this->passAssessment($assessment, $completer, 85);
        }

        if ($requiredAssignment) {
            $this->gradeAssignment($requiredAssignment, $completer, $instructor, round((float) $requiredAssignment->max_points * 0.8, 2));
        }

        // Recompute once everything is in place — this is the moment the enrollment
        // actually flips to Completed and CourseCompleted fires the grade snapshot.
        $learning->recalculate($completer, $course->fresh(['modules.lessons', 'assessments', 'assignments']));

        // ---- The provisional student: every lesson + every required item done EXCEPT
        // the last one (an ungraded submission if there's a required assignment, else the
        // last assessment left unattempted) — a visible "Provisional" summary.
        Enrollment::create([
            'user_id' => $provisional->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            'source' => EnrollmentSource::Self->value,
            'enrolled_at' => now()->subDays(10),
            'progress_percent' => 0,
        ]);

        foreach ($lessons as $lesson) {
            $learning->markComplete($provisional, $lesson);
        }

        $graded = $requiredAssignment ? $requiredAssessments : $requiredAssessments->slice(0, -1);
        foreach ($graded as $assessment) {
            $this->passAssessment($assessment, $provisional, 65);
        }

        if ($requiredAssignment) {
            Submission::create([
                'assignment_id' => $requiredAssignment->id,
                'user_id' => $provisional->id,
                'version' => 1,
                'body' => '<p>Draft in progress — submitted for review.</p>',
                'submitted_at' => now()->subHours(6),
                'is_late' => false,
                'status' => SubmissionStatus::Submitted->value,
            ]);
        }

        $learning->recalculate($provisional, $course->fresh(['modules.lessons', 'assessments', 'assignments']));
    }

    private function passAssessment(Assessment $assessment, User $student, int $percent): void
    {
        $assessment->attempts()->create([
            'user_id' => $student->id,
            'attempt_number' => 1,
            'started_at' => now()->subDays(3),
            'submitted_at' => now()->subDays(3),
            'score' => $percent,
            'max_score' => 100,
            'percentage' => $percent,
            'passed' => $percent >= ($assessment->passing_score ?? 50),
            'status' => AttemptStatus::Graded->value,
            'layout' => ['questions' => []],
        ]);
    }

    private function gradeAssignment(Assignment $assignment, User $student, ?User $instructor, float $points): void
    {
        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'version' => 1,
            'body' => '<p>Completed hand-in.</p>',
            'submitted_at' => now()->subDays(4),
            'is_late' => false,
            'status' => SubmissionStatus::Graded->value,
        ]);

        Grade::create([
            'submission_id' => $submission->id,
            'grader_id' => $instructor?->id ?? $student->id,
            'criterion_scores' => null,
            'points_total' => $points,
            'feedback' => '<p>Solid work — well organised and on point.</p>',
            'graded_at' => now()->subDays(3),
        ]);
    }
}
