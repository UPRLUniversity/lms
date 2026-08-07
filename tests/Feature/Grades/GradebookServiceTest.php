<?php

namespace Tests\Feature\Grades;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Grade;
use App\Models\GradeScale;
use App\Models\Submission;
use App\Services\Grades\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pure aggregation layer: points-weighted course percentage (never the mean of
 * item percentages) and pending-item exclusion.
 */
class GradebookServiceTest extends TestCase
{
    use RefreshDatabase;

    private function scale(): GradeScale
    {
        $scale = GradeScale::factory()->default()->create(['scale_limit' => 5.0]);
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold', 'position' => 1],
            ['label' => 'C', 'grade_point' => 3.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink', 'position' => 2],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson', 'position' => 3],
        ]);

        return $scale->fresh('bands');
    }

    public function test_course_percentage_is_points_weighted_not_a_mean_of_item_percentages(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        // A 10-point quiz at 100%.
        $quiz = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true]);
        Attempt::factory()->create([
            'assessment_id' => $quiz->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => 10,
            'max_score' => 10,
            'percentage' => 100,
        ]);

        // A 90-point exam at 50%.
        $exam = Assignment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true, 'max_points' => 90]);
        $submission = Submission::factory()->create([
            'assignment_id' => $exam->id,
            'user_id' => $student->id,
            'status' => SubmissionStatus::Graded->value,
        ]);
        Grade::factory()->create(['submission_id' => $submission->id, 'points_total' => 45]);

        $service = app(GradebookService::class);
        $summary = $service->summaryFor($student, $course, $this->scale());

        // Mean would be (100 + 50) / 2 = 75%. Points-weighted is (10 + 45) / (10 + 90) = 55%.
        $this->assertEqualsWithDelta(55.0, $summary->percent, 0.01);
        $this->assertNotEqualsWithDelta(75.0, $summary->percent, 0.01);
        $this->assertSame('C', $summary->gradeLabel()); // 55% falls in the 50–59 band (C), not the 75%-mean's B/A
    }

    public function test_pending_items_are_excluded_from_the_computation_but_still_listed(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        // Graded: 100%.
        $graded = Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true]);
        Attempt::factory()->create([
            'assessment_id' => $graded->id,
            'user_id' => $student->id,
            'status' => 'graded',
            'score' => 20,
            'max_score' => 20,
            'percentage' => 100,
        ]);

        // Pending: never attempted.
        Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true]);

        $service = app(GradebookService::class);
        $items = $service->itemsFor($student, $course);
        $this->assertCount(2, $items);

        $summary = $service->summarize($items, $this->scale());

        $this->assertTrue($summary->provisional);
        $this->assertEqualsWithDelta(100.0, $summary->percent, 0.01); // only the graded item counts
        $this->assertFalse($summary->isFinal());
    }

    public function test_a_course_with_zero_gradable_items_has_no_final_summary(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $service = app(GradebookService::class);
        $items = $service->itemsFor($student, $course);
        $this->assertTrue($items->isEmpty());

        $summary = $service->summarize($items, $this->scale());
        $this->assertFalse($summary->hasItems());
        $this->assertFalse($summary->isFinal());
        $this->assertNull($summary->percent);
    }
}
