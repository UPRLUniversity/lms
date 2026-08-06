<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseChange;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\CourseUpdatedNotification;
use App\Services\Courses\CourseChangeService;
use App\Services\Courses\CurriculumChangeClassifier;
use App\Support\Curriculum\ChangeSignificance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Section 16 — telling students what changed, without telling them everything.
 *
 * The value of this feature is entirely in the restraint: if a typo fix reached the whole
 * cohort, the one notification that mattered would be ignored along with the rest.
 */
class CourseChangeNotificationTest extends TestCase
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

    public function test_a_material_change_notifies_every_enrolled_student_exactly_once(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $one = $this->enrolledStudent($course);
        $two = $this->enrolledStudent($course);
        $finished = $this->enrolledStudent($course, EnrollmentStatus::Completed);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'due_at' => now()->addWeek(),
        ]);
        $assignment->due_at = now()->addWeeks(2);

        $this->actingAs($instructor);
        app(CourseChangeService::class)->record(
            $course,
            app(CurriculumChangeClassifier::class)->classify($assignment),
        );

        Notification::assertSentTimes(CourseUpdatedNotification::class, 3);
        Notification::assertSentTo([$one, $two, $finished], CourseUpdatedNotification::class);
    }

    public function test_a_cosmetic_change_tells_nobody_but_is_still_recorded(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $this->enrolledStudent($course);

        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();
        $lesson->content_text = '<p>A fixed typo.</p>';

        $this->actingAs($instructor);
        app(CourseChangeService::class)->record(
            $course,
            app(CurriculumChangeClassifier::class)->classify($lesson),
        );

        Notification::assertNothingSent();

        $this->assertDatabaseHas('course_changes', [
            'course_id' => $course->id,
            'significance' => ChangeSignificance::Cosmetic->value,
        ]);
    }

    /**
     * Sent for real rather than faked, so this asserts the outcome a student would
     * actually see — an empty bell — instead of trusting the channel list.
     */
    public function test_a_student_who_muted_course_updates_gets_no_notification(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $muted = $this->enrolledStudent($course);
        $listening = $this->enrolledStudent($course);

        $muted->setNotificationPreference(NotificationType::CourseUpdated, ['in_app' => false, 'email' => false]);
        $muted->save();

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'due_at' => now()->addWeek(),
        ]);
        $assignment->due_at = now()->addWeeks(3);

        $this->actingAs($instructor);
        app(CourseChangeService::class)->record(
            $course,
            app(CurriculumChangeClassifier::class)->classify($assignment),
        );

        $this->assertSame(0, $muted->notifications()->count());
        $this->assertSame(1, $listening->notifications()->count());
    }

    public function test_a_course_nobody_is_taking_notifies_nobody(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'due_at' => now()->addWeek(),
        ]);
        $assignment->due_at = now()->addWeeks(2);

        $this->actingAs($instructor);
        app(CourseChangeService::class)->record(
            $course,
            app(CurriculumChangeClassifier::class)->classify($assignment),
        );

        Notification::assertNothingSent();
    }

    public function test_one_save_with_several_material_changes_sends_a_single_notification(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $student = $this->enrolledStudent($course);

        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'due_at' => now()->addWeek(),
            'is_required' => false,
        ]);

        $assignment->due_at = now()->addWeeks(2);
        $assignment->is_required = true;

        $described = app(CurriculumChangeClassifier::class)->classify($assignment);
        $this->assertCount(2, $described);

        $this->actingAs($instructor);
        app(CourseChangeService::class)->record($course, $described);

        // Two rows recorded, but the student's bell rings once.
        $this->assertSame(2, CourseChange::where('course_id', $course->id)->count());
        Notification::assertSentToTimes($student, CourseUpdatedNotification::class, 1);
    }

    public function test_hiding_an_item_through_the_endpoint_records_and_announces_it(): void
    {
        Notification::fake();

        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $student = $this->enrolledStudent($course);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create();

        $this->actingAs($instructor)
            ->patchJson(route('courses.curriculum.visibility', [$course, 'lesson', $lesson->id]), [
                'hidden' => true,
                'note' => 'Replaced by the updated reading.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('course_changes', [
            'course_id' => $course->id,
            'action' => 'hidden',
            'significance' => ChangeSignificance::Material->value,
            'note' => 'Replaced by the updated reading.',
        ]);

        Notification::assertSentToTimes($student, CourseUpdatedNotification::class, 1);
        $this->assertDatabaseHas('activity_log', ['event' => 'curriculum.item_hidden']);
    }

    /*
    |--------------------------------------------------------------------------
    | The two read surfaces
    |--------------------------------------------------------------------------
    */

    public function test_a_learner_sees_material_changes_made_since_they_enrolled(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $student = $this->userWithRole(Role::Student->value);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            'enrolled_at' => now()->subDays(3),
        ]);

        CourseChange::factory()->create([
            'course_id' => $course->id,
            'summary' => 'Before they ever joined.',
            'created_at' => now()->subDays(10),
        ]);
        CourseChange::factory()->create([
            'course_id' => $course->id,
            'summary' => 'The deadline moved to Friday.',
            'created_at' => now()->subDay(),
        ]);
        CourseChange::factory()->cosmetic()->create([
            'course_id' => $course->id,
            'summary' => 'A typo nobody needs to hear about.',
            'created_at' => now()->subHours(2),
        ]);

        $this->actingAs($student)
            ->get(route('learn.changes', $course))
            ->assertOk()
            ->assertSee('The deadline moved to Friday.')
            ->assertDontSee('Before they ever joined.')
            ->assertDontSee('A typo nobody needs to hear about.');
    }

    public function test_the_builder_history_shows_everything_including_cosmetic_edits(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);

        CourseChange::factory()->create([
            'course_id' => $course->id,
            'summary' => 'The deadline moved to Friday.',
        ]);
        CourseChange::factory()->cosmetic()->create([
            'course_id' => $course->id,
            'summary' => 'Instructions updated.',
        ]);

        $this->actingAs($instructor)
            ->get(route('courses.changes', $course))
            ->assertOk()
            ->assertSee('The deadline moved to Friday.')
            ->assertSee('Instructions updated.');
    }

    public function test_a_stranger_cannot_read_a_courses_change_history(): void
    {
        $instructor = $this->instructor();
        $course = $this->courseFor($instructor);
        $outsider = $this->userWithRole(Role::Student->value);

        $this->actingAs($outsider)->get(route('learn.changes', $course))->assertForbidden();
        $this->actingAs($outsider)->get(route('courses.changes', $course))->assertForbidden();
    }

    public function test_a_recorded_change_is_never_edited_or_deleted(): void
    {
        $course = Course::factory()->create();
        $change = CourseChange::factory()->create(['course_id' => $course->id]);

        try {
            $change->update(['summary' => 'Rewritten history.']);
            $this->fail('A course change should not be editable.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->expectException(\RuntimeException::class);
        $change->delete();
    }
}
