<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseStatus;
use App\Enums\LessonType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        return $this->userWithRole(Role::Instructor->value);
    }

    public function test_instructor_can_create_a_course_and_becomes_its_lead(): void
    {
        $instructor = $this->instructor();
        $department = Department::factory()->create();

        $response = $this->actingAs($instructor)->post(route('courses.store'), [
            'title' => 'Foundations of Public Relations',
            'code' => 'prl101',
            'department_id' => $department->id,
            'level' => 'undergraduate',
        ]);

        $course = Course::firstOrFail();
        $response->assertRedirect(route('courses.edit', $course));

        $this->assertSame('PRL101', $course->code, 'Code is upper-cased.');
        $this->assertSame(CourseStatus::Draft, $course->status);
        $this->assertSame($instructor->id, $course->created_by);
        $this->assertTrue($course->instructors()->where('users.id', $instructor->id)->wherePivot('is_lead', true)->exists());
    }

    public function test_instructor_can_build_three_modules_with_mixed_lesson_types(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->for(Department::factory())->withInstructor($instructor)->create(['created_by' => $instructor->id]);

        // 3 modules
        foreach (['Intro', 'Core', 'Practice'] as $title) {
            $this->actingAs($instructor)
                ->postJson(route('modules.store', $course), ['title' => $title])
                ->assertOk()->assertJson(['ok' => true]);
        }

        $this->assertSame(3, $course->modules()->count());
        $module = $course->modules()->first();

        // Mixed-type lessons under the first module.
        $this->actingAs($instructor)->postJson(route('lessons.store', [$course, $module]), [
            'title' => 'Welcome video', 'type' => LessonType::Video->value,
            'video_source' => 'embed', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_free_preview' => true,
        ])->assertOk();

        $this->actingAs($instructor)->postJson(route('lessons.store', [$course, $module]), [
            'title' => 'Reading', 'type' => LessonType::Text->value,
            'content_text' => '<p>Hello <script>alert(1)</script></p>',
        ])->assertOk();

        $this->actingAs($instructor)->postJson(route('lessons.store', [$course, $module]), [
            'title' => 'External', 'type' => LessonType::ExternalLink->value,
            'external_url' => 'https://example.com',
        ])->assertOk();

        $this->assertSame(3, $module->lessons()->count());

        $video = $module->lessons()->where('type', LessonType::Video->value)->first();
        $this->assertSame('youtube', $video->video_provider);
        $this->assertTrue($video->is_free_preview);

        // Rich text is sanitized on the way in (RichHtml cast).
        $text = $module->lessons()->where('type', LessonType::Text->value)->first();
        $this->assertStringNotContainsString('<script', $text->content_text);
    }

    public function test_drag_reorder_persists_module_and_lesson_positions(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);

        $moduleA = Module::factory()->for($course)->create(['position' => 1]);
        $moduleB = Module::factory()->for($course)->create(['position' => 2]);

        $a1 = Lesson::factory()->for($moduleA)->create(['position' => 1]);
        $a2 = Lesson::factory()->for($moduleA)->create(['position' => 2]);
        $b1 = Lesson::factory()->for($moduleB)->create(['position' => 1]);

        // Swap module order, and move lesson a2 into module B (after b1).
        $this->actingAs($instructor)->postJson(route('courses.curriculum.reorder', $course), [
            'order' => [
                ['module_id' => $moduleB->id, 'lessons' => [$b1->id, $a2->id]],
                ['module_id' => $moduleA->id, 'lessons' => [$a1->id]],
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, $moduleB->fresh()->position);
        $this->assertSame(2, $moduleA->fresh()->position);

        // a2 re-homed to module B at position 2.
        $a2->refresh();
        $this->assertSame($moduleB->id, $a2->module_id);
        $this->assertSame(2, $a2->position);
        $this->assertSame(1, $b1->fresh()->position);
    }

    public function test_instructor_cannot_touch_another_instructors_course(): void
    {
        $owner = $this->instructor();
        $other = User::factory()->create();
        $other->assignRole(Role::Instructor->value);

        $course = Course::factory()->withInstructor($owner)->create(['created_by' => $owner->id]);
        $module = Module::factory()->for($course)->create();

        $this->actingAs($other)->get(route('courses.edit', $course))->assertForbidden();
        $this->actingAs($other)->postJson(route('modules.store', $course), ['title' => 'Sneaky'])->assertForbidden();
        $this->actingAs($other)->postJson(route('lessons.store', [$course, $module]), [
            'title' => 'Sneaky', 'type' => LessonType::Text->value,
        ])->assertForbidden();
    }

    public function test_reorder_payload_cannot_steal_another_courses_content(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $mine = Module::factory()->for($course)->create(['position' => 1]);

        $foreignCourse = Course::factory()->create();
        $foreignModule = Module::factory()->for($foreignCourse)->create(['position' => 5]);

        $this->actingAs($instructor)->postJson(route('courses.curriculum.reorder', $course), [
            'order' => [['module_id' => $foreignModule->id, 'lessons' => []]],
        ])->assertOk();

        // The foreign module is untouched — the guard ignored it.
        $this->assertSame(5, $foreignModule->fresh()->position);
    }
}
