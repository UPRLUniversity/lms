<?php

namespace Tests\Feature\Grades;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Models\Module;
use App\Services\Courses\LearningService;
use App\Services\Grades\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 14 split the two meanings `is_required` used to carry. A practice quiz can be
 * compulsory to work through (it gates completion) and still stay out of the transcript
 * (it contributes nothing to the course grade).
 */
class GradeContributionTest extends TestCase
{
    use RefreshDatabase;

    private function scale(): GradeScale
    {
        $scale = GradeScale::factory()->default()->create(['scale_limit' => 5.0]);
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'F', 'grade_point' => 0.0, 'min_percent' => 0, 'max_percent' => 69, 'color' => 'crimson', 'position' => 1],
        ]);

        return $scale->fresh('bands');
    }

    public function test_an_item_excluded_from_the_grade_still_gates_progress_but_leaves_the_total_alone(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        // Counts: 100% of 10 points.
        $graded = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'is_required' => true,
            'counts_toward_grade' => true,
        ]);
        Attempt::factory()->create([
            'assessment_id' => $graded->id, 'user_id' => $student->id,
            'status' => 'graded', 'score' => 10, 'max_score' => 10, 'percentage' => 100, 'passed' => true,
        ]);

        // Practice: required, but deliberately kept out of the grade — and scored badly,
        // so if it leaked in the total would drop sharply.
        $practice = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'is_required' => true,
            'counts_toward_grade' => false,
        ]);
        Attempt::factory()->create([
            'assessment_id' => $practice->id, 'user_id' => $student->id,
            'status' => 'graded', 'score' => 0, 'max_score' => 90, 'percentage' => 0, 'passed' => true,
        ]);

        $gradebook = app(GradebookService::class);
        $items = $gradebook->itemsFor($student, $course);

        $this->assertSame([$graded->id], $items->map(fn ($i) => $i->model->id)->all());
        $this->assertEqualsWithDelta(100.0, $gradebook->summaryFor($student, $course, $this->scale(), $items)->percent, 0.01);
    }

    public function test_a_practice_quiz_still_blocks_completion_while_unpassed(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $module = Module::factory()->for($course)->create(['position' => 1]);

        Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'is_required' => true,
            'counts_toward_grade' => false,
        ]);

        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $snapshot = app(LearningService::class)->snapshot($student, $course);

        // Nothing passed yet: the required practice quiz keeps the course incomplete,
        // exactly as it would if it were graded.
        $this->assertFalse($snapshot->isCourseComplete());
    }

    public function test_an_assignment_can_be_required_without_counting_toward_the_grade(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        Assignment::factory()->published()->create([
            'course_id' => $course->id,
            'is_required' => true,
            'counts_toward_grade' => false,
            'max_points' => 50,
        ]);

        $this->assertTrue(app(GradebookService::class)->itemsFor($student, $course)->isEmpty());
    }

    public function test_the_two_switches_save_independently_from_the_builder(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'is_required' => true,
            'counts_toward_grade' => true,
        ]);

        $this->actingAs($instructor)->put(route('assessments.update', [$course, $assessment]), [
            'title' => $assessment->title,
            'selection_mode' => 'fixed',
            'passing_score' => 70,
            'review_policy' => 'immediately',
            'is_required' => '1',
            'counts_toward_grade' => '0',
        ])->assertRedirect();

        $assessment->refresh();
        $this->assertTrue($assessment->is_required);
        $this->assertFalse($assessment->counts_toward_grade);
    }
}
