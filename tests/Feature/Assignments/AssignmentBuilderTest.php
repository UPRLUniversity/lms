<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function instructorWithCourse(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);

        return [$instructor, $course];
    }

    public function test_instructor_creates_an_assignment_and_it_appears_in_the_curriculum(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $module = Module::factory()->create(['course_id' => $course->id]);

        $response = $this->actingAs($instructor)->post(route('assignments.store', $course), [
            'title' => 'Crisis communication case study',
            'module_id' => $module->id,
        ]);

        $assignment = Assignment::firstOrFail();
        $response->assertRedirect(route('assignments.edit', [$course, $assignment]));

        $this->assertSame($module->id, $assignment->module_id);
        $this->assertSame(AssignmentStatus::Draft, $assignment->status);

        // Visible in the course builder curriculum with its own icon chip.
        $this->actingAs($instructor)->get(route('courses.edit', $course))
            ->assertOk()
            ->assertSee('Crisis communication case study');
    }

    public function test_publish_is_blocked_until_max_points_is_set_and_positive(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assignment = Assignment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'max_points' => null,
        ]);

        // Missing max_points → blocked with a clear message.
        $this->actingAs($instructor)->post(route('assignments.publish', [$course, $assignment]))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'points'));
        $this->assertSame(AssignmentStatus::Draft, $assignment->fresh()->status);

        // Zero max_points → still blocked.
        $assignment->forceFill(['max_points' => 0])->save();
        $this->actingAs($instructor)->post(route('assignments.publish', [$course, $assignment]))
            ->assertSessionHas('error');
        $this->assertSame(AssignmentStatus::Draft, $assignment->fresh()->status);

        // Positive max_points (factory sets instructions) → publishes.
        $assignment->forceFill(['max_points' => 40])->save();
        $this->actingAs($instructor)->post(route('assignments.publish', [$course, $assignment]))
            ->assertSessionHas('status');
        $this->assertSame(AssignmentStatus::Published, $assignment->fresh()->status);
    }

    public function test_settings_update_including_late_policy_and_due_date(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $assignment = Assignment::factory()->create(['course_id' => $course->id, 'created_by' => $instructor->id]);

        $this->actingAs($instructor)->put(route('assignments.update', [$course, $assignment]), [
            'title' => 'Media brief',
            'type' => 'file',
            'due_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'allow_late' => 1,
            'max_points' => 25,
            'is_required' => 0,
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame('Media brief', $assignment->title);
        $this->assertSame('file', $assignment->type->value);
        $this->assertTrue($assignment->allow_late);
        $this->assertFalse($assignment->is_required);
        $this->assertSame('25.00', (string) $assignment->max_points);
        $this->assertNotNull($assignment->due_at);
    }

    public function test_an_unrelated_instructor_cannot_edit_the_assignment(): void
    {
        [, $course] = $this->instructorWithCourse();
        $assignment = Assignment::factory()->create(['course_id' => $course->id]);

        $outsider = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($outsider)->get(route('assignments.edit', [$course, $assignment]))->assertForbidden();
        $this->actingAs($outsider)->put(route('assignments.update', [$course, $assignment]), [
            'title' => 'Hijacked', 'type' => 'text',
        ])->assertForbidden();
    }

    public function test_auditor_sees_the_builder_read_only(): void
    {
        [, $course] = $this->instructorWithCourse();
        $assignment = Assignment::factory()->create(['course_id' => $course->id]);

        $auditor = $this->userWithRole(Role::Auditor->value);

        $this->actingAs($auditor)->get(route('assignments.edit', [$course, $assignment]))->assertOk();
        $this->actingAs($auditor)->put(route('assignments.update', [$course, $assignment]), [
            'title' => 'Nope', 'type' => 'text',
        ])->assertForbidden();
        $this->actingAs($auditor)->post(route('assignments.publish', [$course, $assignment]))->assertForbidden();
    }

    public function test_students_cannot_open_a_draft_assignment(): void
    {
        [, $course] = $this->instructorWithCourse();
        $assignment = Assignment::factory()->create(['course_id' => $course->id]);

        $student = $this->userWithRole(Role::Student->value);
        \App\Models\Enrollment::factory()->status(\App\Enums\EnrollmentStatus::Active)
            ->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->get(route('assignments.show', [$course, $assignment]))->assertForbidden();
    }
}
