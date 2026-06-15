<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseStatus;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePublishingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A draft course that satisfies every publish rule (module, lesson, summary, cover).
     */
    private function publishableCourse(User $instructor): Course
    {
        $course = Course::factory()->withInstructor($instructor)->create([
            'created_by' => $instructor->id,
            'status' => CourseStatus::Draft->value,
            'summary' => 'A real summary.',
        ]);

        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->create();

        Media::factory()->for($course, 'mediable')->create(['purpose' => MediaPurpose::CourseCovers]);

        return $course->refresh();
    }

    public function test_submission_is_blocked_until_publish_rules_are_met(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->withInstructor($instructor)->create([
            'created_by' => $instructor->id,
            'status' => CourseStatus::Draft->value,
            'summary' => null,
        ]);

        $this->actingAs($instructor)
            ->from(route('courses.edit', $course))
            ->post(route('courses.submit', $course))
            ->assertRedirect(route('courses.edit', $course))
            ->assertSessionHasErrors('publish');

        $this->assertSame(CourseStatus::Draft, $course->fresh()->status);
    }

    public function test_complete_course_can_be_submitted_for_review(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = $this->publishableCourse($instructor);

        $this->actingAs($instructor)->post(route('courses.submit', $course));

        $this->assertSame(CourseStatus::Review, $course->fresh()->status);
    }

    public function test_admin_publishes_a_course_in_review_and_it_reaches_the_catalogue(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $course = $this->publishableCourse($instructor);
        $course->update(['status' => CourseStatus::Review->value]);

        $this->actingAs($admin)->post(route('courses.publish', $course));

        $course->refresh();
        $this->assertSame(CourseStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);

        $this->get(route('catalogue.index'))->assertSee($course->title);
    }

    public function test_admin_returns_a_course_with_a_required_note_visible_to_the_instructor(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        $course = $this->publishableCourse($instructor);
        $course->update(['status' => CourseStatus::Review->value]);

        // A note is required.
        $this->actingAs($admin)->from(route('courses.edit', $course))
            ->post(route('courses.return', $course), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');
        $this->assertSame(CourseStatus::Review, $course->fresh()->status);

        // With a note, it returns to draft and stores the note.
        $this->actingAs($admin)->post(route('courses.return', $course), [
            'review_note' => 'Please expand Module 2.',
        ]);

        $course->refresh();
        $this->assertSame(CourseStatus::Draft, $course->status);
        $this->assertSame('Please expand Module 2.', $course->review_note);

        // The instructor sees the note in-app on the builder page.
        $this->actingAs($instructor)->get(route('courses.edit', $course))
            ->assertSee('Please expand Module 2.');
    }

    public function test_resubmitting_clears_the_previous_review_note(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = $this->publishableCourse($instructor);
        $course->update(['status' => CourseStatus::Draft->value, 'review_note' => 'Old note.']);

        $this->actingAs($instructor)->post(route('courses.submit', $course));

        $course->refresh();
        $this->assertSame(CourseStatus::Review, $course->status);
        $this->assertNull($course->review_note);
    }

    public function test_publish_validation_rejects_an_empty_course_even_in_review(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->review()->create(['summary' => null]);

        $this->actingAs($admin)->from(route('courses.edit', $course))
            ->post(route('courses.publish', $course))
            ->assertSessionHasErrors('publish');

        $this->assertSame(CourseStatus::Review, $course->fresh()->status);
    }

    public function test_instructor_cannot_publish_their_own_course(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = $this->publishableCourse($instructor);
        $course->update(['status' => CourseStatus::Review->value]);

        $this->actingAs($instructor)->post(route('courses.publish', $course))->assertForbidden();
        $this->assertSame(CourseStatus::Review, $course->fresh()->status);
    }

    public function test_admin_can_archive_and_restore_a_published_course(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->create();

        $this->actingAs($admin)->post(route('courses.archive', $course));
        $this->assertSame(CourseStatus::Archived, $course->fresh()->status);

        $this->actingAs($admin)->post(route('courses.restore', $course));
        $this->assertSame(CourseStatus::Published, $course->fresh()->status);
    }
}
