<?php

namespace Tests\Feature\Assessments;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionDifficulty;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AssessmentPoolRule;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Services\Assessments\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoolingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pooled_attempts_draw_different_sets_that_obey_the_rules(): void
    {
        $course = Course::factory()->published()->create();
        $category = QuestionCategory::factory()->create(['course_id' => $course->id]);

        // A generous pool so two random draws of 5 are very unlikely to match.
        Question::factory()->count(20)->mcqSingle()->create([
            'course_id' => $course->id, 'category_id' => $category->id, 'difficulty' => QuestionDifficulty::Easy->value,
        ]);

        $assessment = Assessment::factory()->published()->pooled()->create(['course_id' => $course->id]);
        AssessmentPoolRule::factory()->create([
            'assessment_id' => $assessment->id, 'category_id' => $category->id,
            'difficulty' => QuestionDifficulty::Easy->value, 'count' => 5,
        ]);

        $service = app(AttemptService::class);

        $a = $this->student($course);
        $b = $this->student($course);

        $attemptA = $service->startAttempt($assessment, $a);
        $attemptB = $service->startAttempt($assessment, $b);

        $setA = $attemptA->questionIds();
        $setB = $attemptB->questionIds();

        // Each obeys the rule: exactly 5 questions, all from the category.
        $this->assertCount(5, $setA);
        $this->assertCount(5, $setB);
        $this->assertSame(5, Question::whereIn('id', $setA)->where('category_id', $category->id)->count());

        // The two draws differ (astronomically unlikely to be identical from 20).
        $this->assertNotEquals($setA, $setB);
    }

    public function test_publish_is_blocked_when_the_pool_is_too_small(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->create(['created_by' => $instructor->id]);
        $category = QuestionCategory::factory()->create(['course_id' => $course->id]);
        Question::factory()->count(2)->mcqSingle()->create(['course_id' => $course->id, 'category_id' => $category->id]);

        $assessment = Assessment::factory()->pooled()->create(['course_id' => $course->id]);
        AssessmentPoolRule::factory()->create([
            'assessment_id' => $assessment->id, 'category_id' => $category->id, 'difficulty' => null, 'count' => 10,
        ]);

        $this->actingAs($instructor)->post(route('assessments.publish', [$course, $assessment]))
            ->assertSessionHas('error');

        $this->assertFalse($assessment->fresh()->isPublished());
    }

    private function student(Course $course): User
    {
        $user = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $user->id, 'course_id' => $course->id]);

        return $user;
    }
}
