<?php

namespace Tests\Feature\Assessments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\User;
use App\Services\Assessments\KnowledgeGainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeGainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Course, 1: Module, 2: Assessment, 3: Assessment}
     */
    private function prePostModule(): array
    {
        $course = Course::factory()->published()->create();
        $module = Module::factory()->for($course)->create();
        $pre = Assessment::factory()->published()->preModule($module->id)->create(['course_id' => $course->id]);
        $post = Assessment::factory()->published()->postModule($module->id)->create(['course_id' => $course->id]);

        return [$course, $module, $pre, $post];
    }

    private function gradedAttempt(Assessment $assessment, User $user, int $pct): void
    {
        Attempt::factory()->graded($pct, $pct >= $assessment->passing_score)->create([
            'assessment_id' => $assessment->id, 'user_id' => $user->id, 'attempt_number' => 1,
        ]);
    }

    public function test_gain_math_for_one_student(): void
    {
        [$course, $module, $pre, $post] = $this->prePostModule();
        $student = User::factory()->create();

        $this->gradedAttempt($pre, $student, 50);
        $this->gradedAttempt($post, $student, 90);

        $gain = app(KnowledgeGainService::class)->forStudentModule($student, $module);

        $this->assertSame(50, $gain['pre']);
        $this->assertSame(90, $gain['post']);
        $this->assertSame(40, $gain['gain']);
    }

    public function test_gain_is_null_until_both_attempts_exist(): void
    {
        [$course, $module, $pre, $post] = $this->prePostModule();
        $student = User::factory()->create();

        $this->gradedAttempt($pre, $student, 50); // only the pre

        $this->assertNull(app(KnowledgeGainService::class)->forStudentModule($student, $module));
    }

    public function test_class_average_gain_over_students_who_did_both(): void
    {
        [$course, $module, $pre, $post] = $this->prePostModule();

        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create(); // only did the pre — excluded from the average

        $this->gradedAttempt($pre, $a, 50);
        $this->gradedAttempt($post, $a, 90);
        $this->gradedAttempt($pre, $b, 70);
        $this->gradedAttempt($post, $b, 80);
        $this->gradedAttempt($pre, $c, 40);

        $avg = app(KnowledgeGainService::class)->classAverageForModule($module);

        $this->assertSame(2, $avg['students']);
        $this->assertSame(60, $avg['pre']);   // (50 + 70) / 2
        $this->assertSame(85, $avg['post']);  // (90 + 80) / 2
        $this->assertSame(25, $avg['gain']);
    }

    public function test_result_screen_renders_the_gain_card(): void
    {
        [$course, $module, $pre, $post] = $this->prePostModule();
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->gradedAttempt($pre, $student, 50);
        $postAttempt = Attempt::factory()->graded(90, true)->create([
            'assessment_id' => $post->id, 'user_id' => $student->id, 'attempt_number' => 1,
        ]);

        $this->actingAs($student)->get(route('attempts.result', $postAttempt))
            ->assertOk()
            ->assertSee('Knowledge gain')
            ->assertSee('+40');
    }
}
