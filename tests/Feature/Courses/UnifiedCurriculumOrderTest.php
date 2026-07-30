<?php

namespace Tests\Feature\Courses;

use App\Enums\AssessmentPlacement;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Section 14: lessons, quizzes and assignments share one position ladder per bucket
 * (a module, or the course-level bucket), so a quiz can sit between lesson 2 and
 * lesson 3 — and the student sees the same sequence the instructor dragged.
 */
class UnifiedCurriculumOrderTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        return $this->userWithRole(Role::Instructor->value);
    }

    /**
     * @param  list<array{type: string, id: int}>  $items
     */
    private function reorder(User $actor, Course $course, ?int $moduleId, array $items, array $extraBuckets = []): TestResponse
    {
        return $this->actingAs($actor)->postJson(route('courses.curriculum.reorder', $course), [
            'order' => array_merge([['module_id' => $moduleId, 'items' => $items]], $extraBuckets),
        ]);
    }

    public function test_a_quiz_dropped_between_two_lessons_persists_in_that_slot(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        $l1 = Lesson::factory()->for($module)->create(['position' => 1]);
        $l2 = Lesson::factory()->for($module)->create(['position' => 2]);
        $l3 = Lesson::factory()->for($module)->create(['position' => 3]);
        $quiz = Assessment::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'placement' => AssessmentPlacement::PostModule->value,
            'position' => 1,
        ]);

        $this->reorder($instructor, $course, $module->id, [
            ['type' => 'lesson', 'id' => $l1->id],
            ['type' => 'lesson', 'id' => $l2->id],
            ['type' => 'assessment', 'id' => $quiz->id],
            ['type' => 'lesson', 'id' => $l3->id],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, $l1->fresh()->position);
        $this->assertSame(2, $l2->fresh()->position);
        $this->assertSame(3, $quiz->fresh()->position);
        $this->assertSame(4, $l3->fresh()->position);
    }

    public function test_the_student_player_shows_the_order_the_instructor_dragged(): void
    {
        $instructor = $this->instructor();
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        $l1 = Lesson::factory()->for($module)->create(['position' => 1, 'title' => 'Lesson one']);
        $l2 = Lesson::factory()->for($module)->create(['position' => 2, 'title' => 'Lesson two']);
        $quiz = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'placement' => AssessmentPlacement::PostModule->value,
            'position' => 3,
            'title' => 'Mid-module check',
        ]);

        // Drag the quiz between the two lessons.
        $this->reorder($instructor, $course, $module->id, [
            ['type' => 'lesson', 'id' => $l1->id],
            ['type' => 'assessment', 'id' => $quiz->id],
            ['type' => 'lesson', 'id' => $l2->id],
        ])->assertOk();

        Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $outline = app(LearningService::class)->outline($student, $course->fresh());

        $this->assertSame(
            ['Lesson one', 'Mid-module check', 'Lesson two'],
            $outline->items->map(fn ($item) => $item->title())->all(),
        );
    }

    public function test_placement_is_re_derived_from_where_an_assessment_lands(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        $lesson = Lesson::factory()->for($module)->create(['position' => 1]);
        $quiz = Assessment::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'placement' => AssessmentPlacement::PostModule->value,
            'position' => 2,
        ]);

        // Before the module's first lesson → pre-module.
        $this->reorder($instructor, $course, $module->id, [
            ['type' => 'assessment', 'id' => $quiz->id],
            ['type' => 'lesson', 'id' => $lesson->id],
        ])->assertOk();
        $this->assertSame(AssessmentPlacement::PreModule, $quiz->fresh()->placement);

        // After it → post-module.
        $this->reorder($instructor, $course, $module->id, [
            ['type' => 'lesson', 'id' => $lesson->id],
            ['type' => 'assessment', 'id' => $quiz->id],
        ])->assertOk();
        $this->assertSame(AssessmentPlacement::PostModule, $quiz->fresh()->placement);

        // Dragged out to the course-level bucket → standalone, and detached from the module.
        $this->reorder($instructor, $course, null, [
            ['type' => 'assessment', 'id' => $quiz->id],
        ], [['module_id' => $module->id, 'items' => [['type' => 'lesson', 'id' => $lesson->id]]]])->assertOk();

        $quiz->refresh();
        $this->assertSame(AssessmentPlacement::Standalone, $quiz->placement);
        $this->assertNull($quiz->module_id);
    }

    public function test_a_lessonless_module_keeps_the_authors_pre_module_choice(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        $this->actingAs($instructor)->post(route('assessments.store', $course), [
            'title' => 'Diagnostic',
            'placement' => AssessmentPlacement::PreModule->value,
            'module_id' => $module->id,
        ])->assertRedirect();

        $quiz = Assessment::where('title', 'Diagnostic')->firstOrFail();

        // Nothing to be before or after yet, so position can't answer the question and
        // the stated intent stands.
        $this->assertSame(AssessmentPlacement::PreModule, $quiz->placement);

        // Add a lesson after it and reorder: derivation takes over and agrees.
        $lesson = Lesson::factory()->for($module)->create(['position' => 2]);
        $this->reorder($instructor, $course, $module->id, [
            ['type' => 'assessment', 'id' => $quiz->id],
            ['type' => 'lesson', 'id' => $lesson->id],
        ])->assertOk();

        $this->assertSame(AssessmentPlacement::PreModule, $quiz->fresh()->placement);
    }

    public function test_an_assignment_moved_to_another_module_re_homes(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $moduleA = Module::factory()->for($course)->create(['position' => 1]);
        $moduleB = Module::factory()->for($course)->create(['position' => 2]);

        $lessonB = Lesson::factory()->for($moduleB)->create(['position' => 1]);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'module_id' => $moduleA->id,
            'position' => 1,
        ]);

        $this->actingAs($instructor)->postJson(route('courses.curriculum.reorder', $course), [
            'order' => [
                ['module_id' => $moduleA->id, 'items' => []],
                ['module_id' => $moduleB->id, 'items' => [
                    ['type' => 'lesson', 'id' => $lessonB->id],
                    ['type' => 'assignment', 'id' => $assignment->id],
                ]],
            ],
        ])->assertOk();

        $assignment->refresh();
        $this->assertSame($moduleB->id, $assignment->module_id);
        $this->assertSame(2, $assignment->position);
    }

    public function test_a_lesson_cannot_be_parked_in_the_course_level_bucket(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);
        $lesson = Lesson::factory()->for($module)->create(['position' => 1]);

        $this->reorder($instructor, $course, null, [
            ['type' => 'lesson', 'id' => $lesson->id],
        ])->assertOk();

        // Still in its module — a lesson has nowhere to live outside one.
        $this->assertSame($module->id, $lesson->fresh()->module_id);
    }

    public function test_a_crafted_payload_cannot_steal_another_courses_assessment(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        $foreignCourse = Course::factory()->create();
        $foreignModule = Module::factory()->for($foreignCourse)->create(['position' => 1]);
        $foreignQuiz = Assessment::factory()->create([
            'course_id' => $foreignCourse->id,
            'module_id' => $foreignModule->id,
            'placement' => AssessmentPlacement::PostModule->value,
            'position' => 7,
        ]);
        $foreignAssignment = Assignment::factory()->create([
            'course_id' => $foreignCourse->id,
            'module_id' => $foreignModule->id,
            'position' => 9,
        ]);

        $this->reorder($instructor, $course, $module->id, [
            ['type' => 'assessment', 'id' => $foreignQuiz->id],
            ['type' => 'assignment', 'id' => $foreignAssignment->id],
        ])->assertOk();

        $foreignQuiz->refresh();
        $this->assertSame($foreignModule->id, $foreignQuiz->module_id);
        $this->assertSame(7, $foreignQuiz->position);
        $this->assertSame(AssessmentPlacement::PostModule, $foreignQuiz->placement);

        $foreignAssignment->refresh();
        $this->assertSame($foreignModule->id, $foreignAssignment->module_id);
        $this->assertSame(9, $foreignAssignment->position);
    }

    public function test_a_new_item_lands_in_the_slot_the_author_clicked(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        $l1 = Lesson::factory()->for($module)->create(['position' => 1]);
        $l2 = Lesson::factory()->for($module)->create(['position' => 2]);

        // "Insert here" between the two lessons is index 1.
        $this->actingAs($instructor)->post(route('assessments.store', $course), [
            'title' => 'Checkpoint',
            'placement' => AssessmentPlacement::PostModule->value,
            'module_id' => $module->id,
            'insert_at' => 1,
        ]);

        $quiz = Assessment::where('title', 'Checkpoint')->firstOrFail();

        $this->assertSame(1, $l1->fresh()->position);
        $this->assertSame(2, $quiz->position);
        $this->assertSame(3, $l2->fresh()->position);
        // Sitting after a lesson makes it post-module, whatever was requested.
        $this->assertSame(AssessmentPlacement::PostModule, $quiz->placement);
    }

    public function test_a_new_lesson_appends_to_the_end_of_the_whole_bucket_not_just_the_lessons(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $module = Module::factory()->for($course)->create(['position' => 1]);

        Lesson::factory()->for($module)->create(['position' => 1]);
        Assessment::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'placement' => AssessmentPlacement::PostModule->value,
            'position' => 2,
        ]);

        $this->actingAs($instructor)->postJson(route('lessons.store', [$course, $module]), [
            'title' => 'Wrap up',
            'type' => 'text',
            'content_text' => '<p>Done.</p>',
        ])->assertOk();

        // Position 3, not 2 — it must not collide with the quiz already sitting there.
        $this->assertSame(3, Lesson::where('title', 'Wrap up')->firstOrFail()->position);
    }

    public function test_the_backfill_migration_preserves_todays_visible_order(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->for($course)->create(['position' => 1]);

        // Three parallel ladders, exactly as they looked before the merge.
        $pre = Assessment::factory()->create([
            'course_id' => $course->id, 'module_id' => $module->id,
            'placement' => AssessmentPlacement::PreModule->value, 'position' => 1,
        ]);
        $post = Assessment::factory()->create([
            'course_id' => $course->id, 'module_id' => $module->id,
            'placement' => AssessmentPlacement::PostModule->value, 'position' => 1,
        ]);
        $l1 = Lesson::factory()->for($module)->create(['position' => 1]);
        $l2 = Lesson::factory()->for($module)->create(['position' => 2]);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id, 'module_id' => $module->id, 'position' => 1,
        ]);
        $standalone = Assessment::factory()->create([
            'course_id' => $course->id, 'module_id' => null,
            'placement' => AssessmentPlacement::Standalone->value, 'position' => 1,
        ]);
        $courseAssignment = Assignment::factory()->create([
            'course_id' => $course->id, 'module_id' => null, 'position' => 1,
        ]);

        $migration = require database_path('migrations/2026_07_31_100100_merge_curriculum_positions.php');
        $migration->up();

        // The legacy render order: pre → lessons → post → assignments.
        $this->assertSame(1, $pre->fresh()->position);
        $this->assertSame(2, $l1->fresh()->position);
        $this->assertSame(3, $l2->fresh()->position);
        $this->assertSame(4, $post->fresh()->position);
        $this->assertSame(5, $assignment->fresh()->position);

        // The course-level bucket gets its own ladder: assessments then assignments.
        $this->assertSame(1, $standalone->fresh()->position);
        $this->assertSame(2, $courseAssignment->fresh()->position);
    }
}
