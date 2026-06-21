<?php

namespace Tests\Feature\Assessments;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private function instructorWithCourse(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->create(['created_by' => $instructor->id]);

        return [$instructor, $course];
    }

    private function store(User $instructor, Course $course, array $payload)
    {
        return $this->actingAs($instructor)->post(route('questions.store', $course), $payload);
    }

    public function test_instructor_authors_all_seven_question_types(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();

        $base = ['difficulty' => 'medium', 'prompt' => '<p>Prompt?</p>', 'points' => 2];

        $cases = [
            'mcq_single' => ['payload' => ['options' => [
                ['text' => 'Right', 'is_correct' => 1], ['text' => 'Wrong', 'is_correct' => 0],
            ]]],
            'mcq_multi' => ['payload' => ['options' => [
                ['text' => 'A', 'is_correct' => 1], ['text' => 'B', 'is_correct' => 1], ['text' => 'C', 'is_correct' => 0],
            ]]],
            'true_false' => ['payload' => ['answer' => 1]],
            'fill_blank' => ['payload' => ['accepted' => ['Paris'], 'case_insensitive' => 1]],
            'matching' => ['payload' => ['pairs' => [
                ['left' => 'NG', 'right' => 'Abuja'], ['left' => 'GH', 'right' => 'Accra'],
            ]]],
            'essay' => ['payload' => ['guidance' => 'Reward clarity.']],
            'scenario' => ['payload' => ['sub_questions' => [
                ['type' => 'mcq_single', 'prompt' => '<p>Sub?</p>', 'points' => 1, 'payload' => ['options' => [
                    ['text' => 'Yes', 'is_correct' => 1], ['text' => 'No', 'is_correct' => 0],
                ]]],
            ]]],
        ];

        foreach ($cases as $type => $extra) {
            $this->store($instructor, $course, array_merge($base, ['type' => $type], $extra))
                ->assertRedirect(route('questions.index', $course));

            $this->assertDatabaseHas('questions', ['course_id' => $course->id, 'type' => $type]);
        }

        $this->assertSame(7, Question::where('course_id', $course->id)->count());

        // Payloads normalised: every option/pair carries a stable id.
        $mcq = Question::where('type', QuestionType::McqSingle->value)->first();
        $this->assertNotEmpty($mcq->options()[0]['id']);
        $this->assertTrue($mcq->options()[0]['is_correct']);
    }

    public function test_mcq_requires_a_correct_option(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();

        $this->store($instructor, $course, [
            'type' => 'mcq_single', 'difficulty' => 'easy', 'prompt' => '<p>Q</p>', 'points' => 1,
            'payload' => ['options' => [['text' => 'A', 'is_correct' => 0], ['text' => 'B', 'is_correct' => 0]]],
        ])->assertSessionHasErrors('payload.options');

        $this->assertSame(0, Question::count());
    }

    public function test_duplicate_clones_a_question(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);

        $this->actingAs($instructor)->post(route('questions.duplicate', [$course, $q]))
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(2, Question::where('course_id', $course->id)->count());
    }

    public function test_delete_is_blocked_when_used_by_a_published_assessment(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);

        $published = Assessment::factory()->published()->create(['course_id' => $course->id]);
        $published->questions()->attach($q->id, ['position' => 0]);

        $this->actingAs($instructor)->delete(route('questions.destroy', [$course, $q]))
            ->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertDatabaseHas('questions', ['id' => $q->id, 'deleted_at' => null]);

        // Once unpublished (draft), the same question deletes (soft).
        $published->update(['status' => 'draft']);
        $this->actingAs($instructor)->delete(route('questions.destroy', [$course, $q]))->assertOk();
        $this->assertSoftDeleted('questions', ['id' => $q->id]);
    }

    public function test_a_student_cannot_reach_the_bank(): void
    {
        [, $course] = $this->instructorWithCourse();
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('questions.index', $course))->assertForbidden();
        $this->actingAs($student)->post(route('questions.store', $course), [
            'type' => 'essay', 'difficulty' => 'easy', 'prompt' => '<p>Q</p>', 'points' => 1,
        ])->assertForbidden();
    }
}
