<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPlayerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course with $count lessons across two modules, returned with its
     * ordered lessons.
     *
     * @return array{0: Course, 1: Collection<int, Lesson>}
     */
    private function makeCourse(int $count = 4, bool $sequential = false): array
    {
        $course = Course::factory()->published()
            ->when($sequential, fn ($f) => $f->sequential())
            ->create();

        $lessons = new Collection;
        $half = (int) ceil($count / 2);

        foreach ([1, 2] as $m) {
            $module = Module::factory()->for($course)->create(['position' => $m]);
            $perModule = $m === 1 ? $half : $count - $half;

            for ($i = 1; $i <= $perModule; $i++) {
                $lessons->push(Lesson::factory()->for($module)->create([
                    'type' => 'text',
                    'content_text' => '<p>Lesson body</p>',
                    'position' => $i,
                ]));
            }
        }

        return [$course, $lessons];
    }

    private function enrol(User $user, Course $course, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        return Enrollment::factory()->status($status)->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    /* ---- access ----------------------------------------------------------- */

    public function test_enrolled_student_can_open_the_player(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse();
        $this->enrol($student, $course);

        $this->actingAs($student)
            ->get(route('learn.show', [$course, $lessons->first()]))
            ->assertOk()
            ->assertSee($lessons->first()->title)
            ->assertSee($course->title);
    }

    public function test_non_enrolled_student_is_forbidden(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse();

        $this->actingAs($student)
            ->get(route('learn.show', [$course, $lessons->first()]))
            ->assertForbidden();
    }

    public function test_lesson_from_another_course_404s(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course] = $this->makeCourse();
        [$other, $otherLessons] = $this->makeCourse();
        $this->enrol($student, $course);

        $this->actingAs($student)
            ->get(route('learn.show', [$course, $otherLessons->first()]))
            ->assertNotFound();
    }

    /* ---- completion → % → enrollment chain -------------------------------- */

    public function test_completing_a_lesson_records_progress_and_advances(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(4);
        $this->enrol($student, $course);

        $this->actingAs($student)
            ->post(route('learn.complete', [$course, $lessons[0]]))
            ->assertRedirect(route('learn.show', [$course, $lessons[1]]));

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
            'status' => LessonProgressStatus::Completed->value,
        ]);

        // 1 of 4 → 25% cached on the enrollment.
        $this->assertSame(25, (int) Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->value('progress_percent'));
    }

    public function test_finishing_every_lesson_completes_the_course_and_enrollment(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(3);
        $enrollment = $this->enrol($student, $course);

        foreach ($lessons as $lesson) {
            $this->actingAs($student)->post(route('learn.complete', [$course, $lesson]));
        }

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
        $this->assertSame(100, (int) $enrollment->progress_percent);
        $this->assertNotNull($enrollment->completed_at);

        // The congratulations page is reachable now.
        $this->actingAs($student)->get(route('learn.congratulations', $course))
            ->assertOk()
            ->assertSee('Congratulations');
    }

    public function test_completion_returns_json_for_async_requests(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(2);
        $this->enrol($student, $course);

        $this->actingAs($student)
            ->postJson(route('learn.complete', [$course, $lessons[0]]))
            ->assertOk()
            ->assertJson(['ok' => true, 'percent' => 50, 'course_completed' => false])
            ->assertJsonPath('next_url', route('learn.show', [$course, $lessons[1]]));

        // Completing the last lesson reports course completion + a congrats URL.
        $this->actingAs($student)
            ->postJson(route('learn.complete', [$course, $lessons[1]]))
            ->assertOk()
            ->assertJson(['percent' => 100, 'course_completed' => true])
            ->assertJsonPath('congratulations_url', route('learn.congratulations', $course));
    }

    public function test_double_completion_is_idempotent(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(4);
        $this->enrol($student, $course);

        // Two rapid submits of the same lesson (a double-click).
        $this->actingAs($student)->post(route('learn.complete', [$course, $lessons[0]]));
        $this->actingAs($student)->post(route('learn.complete', [$course, $lessons[0]]));

        $this->assertSame(1, LessonProgress::where('user_id', $student->id)->where('lesson_id', $lessons[0]->id)->count());
        $this->assertSame(25, (int) Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->value('progress_percent'));
    }

    public function test_marking_incomplete_reverts_a_completed_course(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(2);
        $enrollment = $this->enrol($student, $course);

        foreach ($lessons as $lesson) {
            $this->actingAs($student)->post(route('learn.complete', [$course, $lesson]));
        }
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);

        $this->actingAs($student)->post(route('learn.incomplete', [$course, $lessons[0]]));

        $enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(50, (int) $enrollment->progress_percent);
        $this->assertNull($enrollment->completed_at);
    }

    /* ---- sequential locking ----------------------------------------------- */

    public function test_sequential_first_lesson_is_open(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(4, sequential: true);
        $this->enrol($student, $course);

        $this->actingAs($student)
            ->get(route('learn.show', [$course, $lessons[0]]))
            ->assertOk();
    }

    public function test_sequential_skip_ahead_by_url_is_blocked_server_side(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(4, sequential: true);
        $this->enrol($student, $course);

        // Jumping straight to lesson 3 is bounced back to the first incomplete lesson.
        $this->actingAs($student)
            ->get(route('learn.show', [$course, $lessons[2]]))
            ->assertRedirect(route('learn.show', [$course, $lessons[0]]))
            ->assertSessionHas('error');
    }

    public function test_sequential_unlocks_the_next_lesson_after_completion(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(4, sequential: true);
        $this->enrol($student, $course);

        // Lesson 2 is locked until lesson 1 is done.
        $this->actingAs($student)->get(route('learn.show', [$course, $lessons[1]]))->assertRedirect();

        $this->actingAs($student)->post(route('learn.complete', [$course, $lessons[0]]));

        // Now lesson 2 opens, but lesson 3 stays locked.
        $this->actingAs($student)->get(route('learn.show', [$course, $lessons[1]]))->assertOk();
        $this->actingAs($student)->get(route('learn.show', [$course, $lessons[2]]))->assertRedirect();
    }

    /* ---- resume ----------------------------------------------------------- */

    public function test_resume_deep_links_to_first_incomplete_lesson(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(4);
        $this->enrol($student, $course);

        $this->actingAs($student)->post(route('learn.complete', [$course, $lessons[0]]));

        $this->actingAs($student)
            ->get(route('learn.resume', $course))
            ->assertRedirect(route('learn.show', [$course, $lessons[1]]));
    }

    public function test_resume_when_finished_returns_to_the_start(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(2);
        $this->enrol($student, $course);

        foreach ($lessons as $lesson) {
            $this->actingAs($student)->post(route('learn.complete', [$course, $lesson]));
        }

        $this->actingAs($student)
            ->get(route('learn.resume', $course))
            ->assertRedirect(route('learn.show', [$course, $lessons[0]]));
    }

    public function test_position_endpoint_saves_the_resume_point(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(2);
        $this->enrol($student, $course);

        $this->actingAs($student)
            ->postJson(route('learn.position', [$course, $lessons[0]]), [
                'position_seconds' => 137,
                'seconds_spent' => 137,
            ])
            ->assertNoContent();

        $this->assertSame(137, (int) LessonProgress::where('user_id', $student->id)
            ->where('lesson_id', $lessons[0]->id)
            ->value('last_position_seconds'));
    }

    /* ---- preview & tracking guard ----------------------------------------- */

    public function test_staff_can_preview_but_cannot_track(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        [$course, $lessons] = $this->makeCourse(2);
        $course->instructors()->attach($instructor->id, ['is_lead' => true]);

        // Preview opens (no enrollment) …
        $this->actingAs($instructor)
            ->get(route('learn.show', [$course, $lessons[0]]))
            ->assertOk()
            ->assertSee('previewing');

        // … but marking complete is rejected, and writes nothing.
        $this->actingAs($instructor)
            ->post(route('learn.complete', [$course, $lessons[0]]))
            ->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $instructor->id,
            'lesson_id' => $lessons[0]->id,
        ]);
    }

    /* ---- history & instructor progress ------------------------------------ */

    public function test_learning_history_lists_engaged_courses(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        [$course, $lessons] = $this->makeCourse(2);
        $this->enrol($student, $course);
        $this->actingAs($student)->post(route('learn.complete', [$course, $lessons[0]]));

        $this->actingAs($student)
            ->get(route('learning.history'))
            ->assertOk()
            ->assertSee($course->title)
            ->assertSee('50%');
    }

    public function test_instructor_sees_per_student_progress(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        [$course, $lessons] = $this->makeCourse(2);
        $course->instructors()->attach($instructor->id, ['is_lead' => true]);

        $student = $this->userWithRole(Role::Student->value, ['name' => 'Ada Progress']);
        $this->enrol($student, $course);
        $this->actingAs($student)->post(route('learn.complete', [$course, $lessons[0]]));

        $this->actingAs($instructor)
            ->get(route('courses.progress', $course))
            ->assertOk()
            ->assertSee('Ada Progress')
            ->assertSee('50%');
    }

    public function test_instructor_cannot_view_progress_for_a_course_they_dont_teach(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        [$course] = $this->makeCourse(2);

        $this->actingAs($instructor)
            ->get(route('courses.progress', $course))
            ->assertForbidden();
    }
}
