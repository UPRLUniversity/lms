<?php

namespace Tests\Feature\Courses;

use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Submission;
use App\Models\User;
use App\Services\Assignments\AssignmentBuilderService;
use App\Services\Courses\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 16 — editing a course that students are already on.
 *
 * Two promises, tested here for the first time in this suite: curriculum carrying student
 * work is never destroyed by routine authoring, and a student who has finished is never
 * re-measured against a course that changed afterwards.
 */
class CurriculumChangeSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        return $this->userWithRole(Role::Instructor->value);
    }

    private function courseFor(User $instructor): Course
    {
        return Course::factory()
            ->withInstructor($instructor)
            ->create(['created_by' => $instructor->id]);
    }

    private function enrolledStudent(Course $course, EnrollmentStatus $status = EnrollmentStatus::Active): User
    {
        $student = $this->userWithRole(Role::Student->value);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status->value,
        ]);

        return $student;
    }

    /*
    |--------------------------------------------------------------------------
    | Layer 1 — student work is never destroyed by an edit
    |--------------------------------------------------------------------------
    */

    public function test_a_lesson_a_student_has_worked_through_cannot_be_deleted(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $student = $this->enrolledStudent($course);

        $progress = LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::Completed->value,
            'completed_at' => now(),
        ]);

        $this->actingAs($instructor)
            ->deleteJson(route('lessons.destroy', [$course, $lesson]))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'can_hide' => true]);

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseHas('lesson_progress', ['id' => $progress->id]);
    }

    public function test_deleting_a_module_is_refused_when_anything_beneath_it_holds_student_work(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $student = $this->enrolledStudent($course);

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'status' => LessonProgressStatus::Completed->value,
        ]);

        $this->actingAs($instructor)
            ->deleteJson(route('modules.destroy', [$course, $module]))
            ->assertStatus(422);

        $this->assertDatabaseHas('modules', ['id' => $module->id]);
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
    }

    public function test_an_assignment_with_a_recorded_grade_cannot_be_deleted(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
        ]);
        $student = $this->enrolledStudent($course);

        $submission = Submission::factory()->graded()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
        ]);
        $grade = Grade::factory()->create([
            'submission_id' => $submission->id,
            'grader_id' => $instructor->id,
        ]);

        $this->actingAs($instructor)
            ->delete(route('assignments.destroy', [$course, $assignment]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('submissions', ['id' => $submission->id]);
        $this->assertDatabaseHas('grades', ['id' => $grade->id]);
    }

    public function test_an_assessment_a_student_has_attempted_cannot_be_deleted(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
        ]);
        $student = $this->enrolledStudent($course);

        $attempt = Attempt::factory()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($instructor)
            ->delete(route('assessments.destroy', [$course, $assessment]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseHas('attempts', ['id' => $attempt->id]);
    }

    public function test_untouched_curriculum_still_deletes_normally(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();

        $this->actingAs($instructor)
            ->deleteJson(route('lessons.destroy', [$course, $lesson]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_a_course_with_enrolments_cannot_be_deleted(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $this->enrolledStudent($course);

        $this->actingAs($instructor)
            ->delete(route('courses.destroy', $course))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Layer 1 — hiding is the safe alternative
    |--------------------------------------------------------------------------
    */

    public function test_hiding_a_lesson_removes_it_from_the_learner_outline_but_keeps_the_work(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $keep = Lesson::factory()->for($module)->create(['position' => 1]);
        $hide = Lesson::factory()->for($module)->create(['position' => 2]);
        $student = $this->enrolledStudent($course);

        $progress = LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $hide->id,
            'status' => LessonProgressStatus::Completed->value,
        ]);

        $this->actingAs($instructor)
            ->patchJson(route('courses.curriculum.visibility', [$course, 'lesson', $hide->id]), ['hidden' => true])
            ->assertOk()
            ->assertJson(['ok' => true, 'hidden' => true]);

        $outline = app(LearningService::class)->outline($student, $course->fresh());
        $lessonIds = $outline->items
            ->filter(fn ($i) => $i->kind === 'lesson')
            ->map(fn ($i) => $i->model->id)
            ->all();

        $this->assertContains($keep->id, $lessonIds);
        $this->assertNotContains($hide->id, $lessonIds);

        // The record survives the withdrawal — that is the entire point of hiding.
        $this->assertDatabaseHas('lesson_progress', ['id' => $progress->id]);
        $this->assertDatabaseHas('lessons', ['id' => $hide->id]);
    }

    public function test_hiding_a_module_hides_everything_inside_it_and_restoring_brings_it_back(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->count(3)->create();
        $student = $this->enrolledStudent($course);

        $learning = app(LearningService::class);
        $this->assertCount(3, $learning->outline($student, $course->fresh())->items);

        $this->actingAs($instructor)
            ->patchJson(route('courses.curriculum.visibility', [$course, 'module', $module->id]), ['hidden' => true])
            ->assertOk();

        $this->assertCount(0, $learning->outline($student, $course->fresh())->items);

        $this->actingAs($instructor)
            ->patchJson(route('courses.curriculum.visibility', [$course, 'module', $module->id]), ['hidden' => false])
            ->assertOk();

        $this->assertCount(3, $learning->outline($student, $course->fresh())->items);
    }

    /**
     * The assignment page posts an ordinary form rather than calling the AJAX builder, so
     * the one endpoint has to answer with a redirect there and JSON in the outline.
     */
    public function test_hiding_from_a_plain_form_post_redirects_back_with_a_message(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
        ]);

        $this->actingAs($instructor)
            ->from(route('assignments.edit', [$course, $assignment]))
            ->patch(route('courses.curriculum.visibility', [$course, 'assignment', $assignment->id]), ['hidden' => true])
            ->assertRedirect(route('assignments.edit', [$course, $assignment]))
            ->assertSessionHas('status');

        $this->assertNotNull($assignment->fresh()->hidden_at);
    }

    public function test_visibility_cannot_be_changed_on_another_instructors_course(): void
    {
        $owner = $this->instructor();
        $intruder = $this->userWithRole(Role::Instructor->value);
        $course = $this->courseFor($owner);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();

        $this->actingAs($intruder)
            ->patchJson(route('courses.curriculum.visibility', [$course, 'lesson', $lesson->id]), ['hidden' => true])
            ->assertForbidden();

        $this->assertNull($lesson->fresh()->hidden_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Layer 1 — the points denominator can't move under recorded grades
    |--------------------------------------------------------------------------
    */

    public function test_max_points_cannot_change_once_a_grade_is_recorded(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'max_points' => 20,
        ]);
        $student = $this->enrolledStudent($course);

        $submission = Submission::factory()->graded()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
        ]);
        Grade::factory()->create([
            'submission_id' => $submission->id,
            'grader_id' => $instructor->id,
            'points_total' => 18,
        ]);

        $this->expectException(\DomainException::class);

        app(AssignmentBuilderService::class)->updateSettings($assignment, ['max_points' => 40]);
    }

    public function test_max_points_still_changes_freely_before_anything_is_graded(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'max_points' => 20,
        ]);

        app(AssignmentBuilderService::class)->updateSettings($assignment, ['max_points' => 40]);

        $this->assertEquals(40, $assignment->fresh()->max_points);
    }

    /*
    |--------------------------------------------------------------------------
    | Layer 4 — a finished student is measured against what they finished
    |--------------------------------------------------------------------------
    */

    /**
     * Drives a student to genuine completion through the real service, so the snapshot is
     * written by the same path production uses rather than being hand-built.
     */
    private function completeCourse(User $student, Course $course): Enrollment
    {
        $learning = app(LearningService::class);

        foreach ($learning->sequence($course) as $lesson) {
            $learning->markComplete($student, $lesson);
        }

        return Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->firstOrFail();
    }

    public function test_completing_a_course_freezes_the_curriculum_that_counted(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->for($module)->count(2)->create();
        $student = $this->enrolledStudent($course);

        $enrollment = $this->completeCourse($student, $course);

        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertTrue($enrollment->hasCompletionSnapshot());

        $snapshot = $enrollment->completionSnapshot();
        $this->assertNotNull($snapshot);
        $this->assertEqualsCanonicalizing($lessons->pluck('id')->all(), $snapshot->lessonIds);
    }

    public function test_a_completed_enrolment_is_not_un_completed_by_a_new_required_item(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->count(2)->create();
        $student = $this->enrolledStudent($course);

        $enrollment = $this->completeCourse($student, $course);
        $this->assertSame(100, $enrollment->progress_percent);

        // The instructor adds a new required assignment months later.
        Assignment::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'created_by' => $instructor->id,
            'status' => AssignmentStatus::Published->value,
            'is_required' => true,
        ]);

        app(LearningService::class)->recalculate($student, $course->fresh());

        $after = $enrollment->fresh();
        $this->assertSame(EnrollmentStatus::Completed, $after->status);
        $this->assertSame(100, $after->progress_percent);
        $this->assertNotNull($after->completed_at);
    }

    /**
     * The line the freeze draws: a completed student's OWN work still counts live, so
     * undoing it genuinely un-finishes the course. Only the curriculum is frozen.
     */
    public function test_a_completed_student_undoing_their_own_work_does_reopen_the_course(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->for($module)->count(2)->create();
        $student = $this->enrolledStudent($course);

        $enrollment = $this->completeCourse($student, $course);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);

        app(LearningService::class)->markIncomplete($student, $lessons->first());

        $after = $enrollment->fresh();
        $this->assertSame(EnrollmentStatus::Active, $after->status);
        $this->assertSame(50, (int) $after->progress_percent);
    }

    /**
     * ...and having reopened, they are still measured against the frozen set — the extra
     * required assignment added afterwards does not enlarge their denominator.
     */
    public function test_a_reopened_student_is_still_measured_against_the_frozen_curriculum(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->for($module)->count(2)->create();
        $student = $this->enrolledStudent($course);

        $enrollment = $this->completeCourse($student, $course);
        $learning = app(LearningService::class);

        Assignment::factory()->create([
            'course_id' => $course->id,
            'module_id' => $module->id,
            'created_by' => $instructor->id,
            'status' => AssignmentStatus::Published->value,
            'is_required' => true,
        ]);

        $learning->markIncomplete($student, $lessons->first());
        $this->assertSame(50, (int) $enrollment->fresh()->progress_percent);

        // Re-doing their own work returns them to 100% of what they signed up to.
        $learning->markComplete($student, $lessons->first());

        $after = $enrollment->fresh();
        $this->assertSame(EnrollmentStatus::Completed, $after->status);
        $this->assertSame(100, (int) $after->progress_percent);
    }

    public function test_an_active_student_still_follows_the_live_course(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->for($module)->count(2)->create();
        $student = $this->enrolledStudent($course);

        $learning = app(LearningService::class);
        $learning->markComplete($student, $lessons->first());

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertFalse($enrollment->hasCompletionSnapshot());

        // A third lesson lands mid-course: an active student's denominator does move.
        Lesson::factory()->for($module)->create();
        $learning->recalculate($student, $course->fresh());

        $this->assertSame(33, $enrollment->fresh()->progress_percent);
    }

    public function test_a_completion_snapshot_is_never_rewritten(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->create();
        $student = $this->enrolledStudent($course);

        $enrollment = $this->completeCourse($student, $course);

        $this->expectException(\RuntimeException::class);

        $enrollment->completion_snapshot = ['lesson_ids' => [9999]];
        $enrollment->save();
    }
}
