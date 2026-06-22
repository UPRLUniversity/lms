<?php

namespace Tests\Feature\Assessments;

use App\Enums\AssessmentPlacement;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function instructorWithCourse(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->create(['created_by' => $instructor->id]);

        return [$instructor, $course];
    }

    public function test_create_at_a_pre_module_attachment_point(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $module = Module::factory()->for($course)->create();

        $this->actingAs($instructor)->post(route('assessments.store', $course), [
            'title' => 'Diagnostic', 'placement' => 'pre_module', 'module_id' => $module->id, 'selection_mode' => 'fixed',
        ])->assertRedirect();

        $this->assertDatabaseHas('assessments', [
            'course_id' => $course->id, 'module_id' => $module->id, 'placement' => AssessmentPlacement::PreModule->value,
        ]);
    }

    public function test_fixed_questions_sync_with_a_running_points_total(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $q1 = Question::factory()->mcqSingle()->points(2)->create(['course_id' => $course->id]);
        $q2 = Question::factory()->mcqSingle()->points(3)->create(['course_id' => $course->id]);

        $this->actingAs($instructor)->putJson(route('assessments.questions.sync', [$course, $assessment]), [
            'questions' => [
                ['id' => $q2->id], // ordered q2 then q1
                ['id' => $q1->id, 'points_override' => 5],
            ],
        ])->assertOk()->assertJson(['ok' => true, 'count' => 2, 'total_points' => 8]); // 3 + 5

        $this->assertSame([$q2->id, $q1->id], $assessment->questions()->pluck('questions.id')->all());
    }

    public function test_a_question_from_another_course_is_not_attachable(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $foreign = Question::factory()->mcqSingle()->create(); // different course

        $this->actingAs($instructor)->putJson(route('assessments.questions.sync', [$course, $assessment]), [
            'questions' => [['id' => $foreign->id]],
        ])->assertOk();

        $this->assertSame(0, $assessment->questions()->count());
    }

    public function test_publish_requires_at_least_one_question(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        $this->actingAs($instructor)->post(route('assessments.publish', [$course, $assessment]))
            ->assertSessionHas('error');
        $this->assertFalse($assessment->fresh()->isPublished());

        $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($q->id, ['position' => 0]);

        $this->actingAs($instructor)->post(route('assessments.publish', [$course, $assessment]))
            ->assertSessionHas('status');
        $this->assertTrue($assessment->fresh()->isPublished());
    }

    public function test_builder_and_preview_render(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $q = Question::factory()->matching()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($q->id, ['position' => 0]);

        $this->actingAs($instructor)->get(route('assessments.edit', [$course, $assessment]))->assertOk();
        $this->actingAs($instructor)->get(route('assessments.preview', [$course, $assessment]))->assertOk();
    }

    public function test_settings_update_persists(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        $this->actingAs($instructor)->put(route('assessments.update', [$course, $assessment]), [
            'title' => 'Final exam', 'selection_mode' => 'fixed', 'passing_score' => 65,
            'time_limit_minutes' => 45, 'max_attempts' => 2, 'review_policy' => 'after_close',
            'shuffle_questions' => '1', 'shuffle_options' => '1', 'show_explanations' => '1', 'is_required' => '1',
        ])->assertRedirect();

        $assessment->refresh();
        $this->assertSame(65, $assessment->passing_score);
        $this->assertSame(45, $assessment->time_limit_minutes);
        $this->assertSame(2, $assessment->max_attempts);
        $this->assertTrue($assessment->shuffle_questions);
        $this->assertSame('after_close', $assessment->review_policy->value);
    }
}
