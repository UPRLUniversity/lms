<?php

namespace Tests\Feature\Grades;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeScale;
use App\Models\Submission;
use App\Services\Courses\LearningService;
use App\Services\Grades\CourseGradeRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The completion snapshot: written once on Enrollment → Completed (idempotent),
 * immutable thereafter even if the scale is edited later, and skipped entirely for a
 * course with no gradable items.
 */
class CourseGradeRecordTest extends TestCase
{
    use RefreshDatabase;

    private function scale(): GradeScale
    {
        $scale = GradeScale::factory()->default()->create(['scale_limit' => 5.0]);
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 69, 'color' => 'crimson', 'position' => 1],
        ]);

        return $scale;
    }

    public function test_completion_writes_the_snapshot_exactly_once(): void
    {
        $this->scale();
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'passing_score' => 50]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        Attempt::factory()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => 90,
            'max_score' => 100,
            'percentage' => 90,
            'passed' => true,
        ]);

        $learning = app(LearningService::class);
        $enrollment = $learning->recalculate($student, $course);

        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertDatabaseCount('course_grade_records', 1);

        $record = CourseGradeRecord::query()->where('user_id', $student->id)->where('course_id', $course->id)->current()->first();
        $this->assertNotNull($record);
        $this->assertSame('A', $record->grade_label);
        $this->assertEqualsWithDelta(90.0, (float) $record->final_percent, 0.01);

        // A repeated recalculation (already Completed) must not duplicate the snapshot.
        $learning->recalculate($student, $course);
        $this->assertDatabaseCount('course_grade_records', 1);
    }

    public function test_editing_the_scale_after_completion_does_not_change_the_recorded_grade(): void
    {
        $scale = $this->scale();
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'passing_score' => 50]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);
        Attempt::factory()->create([
            'assessment_id' => $assessment->id, 'user_id' => $student->id, 'status' => 'graded',
            'score' => 75, 'max_score' => 100, 'percentage' => 75, 'passed' => true,
        ]);

        app(LearningService::class)->recalculate($student, $course);

        $record = CourseGradeRecord::query()->where('user_id', $student->id)->where('course_id', $course->id)->current()->firstOrFail();
        $this->assertSame('A', $record->grade_label);
        $originalPercent = (float) $record->final_percent;

        // Now edit the scale's bands so 75% would map to F instead of A.
        $scale->bands()->delete();
        $scale->bands()->createMany([
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 100, 'color' => 'crimson', 'position' => 0],
        ]);
        // (A single all-covering band is invalid for a real save via GradeScaleService,
        // but here we're proving the RECORD doesn't re-read the scale at all.)

        $record->refresh();
        $this->assertSame('A', $record->grade_label);
        $this->assertEqualsWithDelta($originalPercent, (float) $record->final_percent, 0.01);
    }

    public function test_a_course_with_zero_gradable_items_produces_no_snapshot(): void
    {
        $this->scale();
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $record = app(CourseGradeRecordService::class)->recordCompletion($student, $course);

        $this->assertNull($record);
        $this->assertDatabaseCount('course_grade_records', 0);
    }

    public function test_grading_the_pending_item_finalises_the_summary_and_snapshots_once(): void
    {
        $this->scale();
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'passing_score' => 50]);
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'max_points' => 50]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        Attempt::factory()->create([
            'assessment_id' => $assessment->id, 'user_id' => $student->id, 'status' => 'graded',
            'score' => 80, 'max_score' => 100, 'percentage' => 80, 'passed' => true,
        ]);
        $submission = Submission::factory()->create([
            'assignment_id' => $assignment->id, 'user_id' => $student->id,
            'status' => SubmissionStatus::Submitted->value,
        ]);

        $learning = app(LearningService::class);
        $enrollment = $learning->recalculate($student, $course);

        // Still provisional — the assignment is pending — so no snapshot yet.
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertDatabaseCount('course_grade_records', 0);

        // Grade it (mirrors AssignmentGradingService::grade()).
        Grade::factory()->create(['submission_id' => $submission->id, 'points_total' => 40]);
        $submission->forceFill(['status' => SubmissionStatus::Graded->value])->save();

        $enrollment = $learning->recalculate($student, $course);

        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertDatabaseCount('course_grade_records', 1);
    }
}
