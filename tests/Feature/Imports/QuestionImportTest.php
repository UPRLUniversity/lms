<?php

namespace Tests\Feature\Imports;

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Imports\QuestionImport;
use App\Models\Course;
use App\Models\Question;
use App\Support\Import\ImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private function importCsv(Course $course, string $csv, ?object $actor = null): array
    {
        $actor ??= $this->userWithRole(Role::Admin->value);
        $path = tempnam(sys_get_temp_dir(), 'q').'.csv';
        file_put_contents($path, $csv);

        return app(ImportRunner::class)->import(app(QuestionImport::class), $path, $course, $actor);
    }

    private function analyse(Course $course, string $csv): array
    {
        $path = tempnam(sys_get_temp_dir(), 'q').'.csv';
        file_put_contents($path, $csv);

        return app(ImportRunner::class)->analyse(app(QuestionImport::class), $path, $course, $this->userWithRole(Role::Admin->value));
    }

    public function test_a_single_answer_mcq_imports_with_its_answer_key(): void
    {
        $course = Course::factory()->create();

        $this->importCsv($course, "type,question,option_a,option_b,option_c,correct,points\n"
            ."mcq_single,Which is a public?,Employees,A press release,A budget,A,2\n");

        $question = Question::where('course_id', $course->id)->firstOrFail();

        $this->assertSame(QuestionType::McqSingle, $question->type);
        $this->assertSame('2.00', $question->points);
        $this->assertCount(3, $question->payload['options']);
        $this->assertTrue($question->payload['options'][0]['is_correct']);
        $this->assertFalse($question->payload['options'][1]['is_correct']);
    }

    public function test_a_multi_select_takes_several_letters(): void
    {
        $course = Course::factory()->create();

        $this->importCsv($course, "type,question,option_a,option_b,option_c,option_d,correct\n"
            ."mcq_multi,Which are earned media?,A feature,A billboard,A blog review,A sponsored post,\"A,C\"\n");

        $options = Question::where('course_id', $course->id)->firstOrFail()->payload['options'];

        $this->assertTrue($options[0]['is_correct']);
        $this->assertFalse($options[1]['is_correct']);
        $this->assertTrue($options[2]['is_correct']);
        $this->assertFalse($options[3]['is_correct']);
    }

    public function test_a_single_answer_question_with_two_correct_letters_is_refused(): void
    {
        $course = Course::factory()->create();

        $report = $this->analyse($course, "type,question,option_a,option_b,correct\n"
            ."mcq_single,Pick one,Alpha,Beta,\"A,B\"\n");

        $this->assertSame(QuestionImport::MULTI_ON_SINGLE, $report['rows'][0]->problem);
        $this->assertSame(0, $report['counts']['valid']);
    }

    public function test_an_answer_naming_a_blank_option_is_refused(): void
    {
        $course = Course::factory()->create();

        // "D" names a column that has no text — almost always an off-by-one in the sheet.
        $report = $this->analyse($course, "type,question,option_a,option_b,correct\n"
            ."mcq_single,Pick one,Alpha,Beta,D\n");

        $this->assertSame(QuestionImport::BAD_CORRECT, $report['rows'][0]->problem);
    }

    public function test_true_false_imports_the_stated_answer(): void
    {
        $course = Course::factory()->create();

        $this->importCsv($course, "type,question,correct\ntrue_false,The sky is green.,false\n");

        $payload = Question::where('course_id', $course->id)->firstOrFail()->payload;

        $this->assertFalse($payload['options'][0]['is_correct'], 'the True option must not be correct');
        $this->assertTrue($payload['options'][1]['is_correct']);
    }

    public function test_fill_blank_accepts_several_answers(): void
    {
        $course = Course::factory()->create();

        $this->importCsv($course, "type,question,correct\nfill_blank,The motto ends with ______.,\"Character,character\"\n");

        $payload = Question::where('course_id', $course->id)->firstOrFail()->payload;

        $this->assertSame(['Character', 'character'], $payload['accepted']);
        $this->assertTrue($payload['case_insensitive']);
    }

    public function test_an_essay_needs_only_a_prompt(): void
    {
        $course = Course::factory()->create();

        $result = $this->importCsv($course, "type,question,points\nessay,Discuss the ethics of advocacy.,10\n");

        $this->assertSame(1, $result['imported']);
        $this->assertSame(QuestionType::Essay, Question::where('course_id', $course->id)->firstOrFail()->type);
    }

    public function test_matching_and_scenario_are_refused_with_an_explanation(): void
    {
        $course = Course::factory()->create();

        $report = $this->analyse($course, "type,question,correct\nmatching,Pair these,x\nscenario,A case,y\n");

        $this->assertSame(QuestionImport::UNSUPPORTED_TYPE, $report['rows'][0]->problem);
        $this->assertSame(QuestionImport::UNSUPPORTED_TYPE, $report['rows'][1]->problem);
        $this->assertStringContainsString('editor', app(ImportRunner::class)->label(app(QuestionImport::class), QuestionImport::UNSUPPORTED_TYPE));
    }

    public function test_categories_are_created_once_and_reused_case_insensitively(): void
    {
        $course = Course::factory()->create();

        $this->importCsv($course, "type,question,option_a,option_b,correct,category\n"
            ."mcq_single,First,Alpha,Beta,A,PR Theory\n"
            ."mcq_single,Second,Alpha,Beta,B,pr theory\n");

        $this->assertDatabaseCount('question_categories', 1);
        $this->assertSame(2, Question::where('course_id', $course->id)->count());
    }

    public function test_the_same_prompt_twice_in_one_file_imports_once(): void
    {
        $course = Course::factory()->create();

        $report = $this->analyse($course, "type,question,option_a,option_b,correct\n"
            ."mcq_single,Which is a public?,Alpha,Beta,A\n"
            ."mcq_single,Which is a public?,Alpha,Beta,A\n");

        $this->assertTrue($report['rows'][0]->isOk());
        $this->assertSame(QuestionImport::DUPLICATE, $report['rows'][1]->problem);
    }

    public function test_a_prompt_containing_markup_is_escaped_not_executed(): void
    {
        $course = Course::factory()->create();

        $this->importCsv($course, "type,question,points\nessay,\"Explain <script>alert(1)</script> injection.\",5\n");

        $prompt = Question::where('course_id', $course->id)->firstOrFail()->prompt;

        $this->assertStringNotContainsString('<script', $prompt);
        $this->assertStringContainsString('injection', $prompt);
    }

    public function test_a_bad_points_value_is_refused(): void
    {
        $course = Course::factory()->create();

        $report = $this->analyse($course, "type,question,points\nessay,Discuss.,not-a-number\n");

        $this->assertSame(QuestionImport::BAD_POINTS, $report['rows'][0]->problem);
    }

    public function test_only_someone_who_can_edit_the_course_may_import_into_it(): void
    {
        $course = Course::factory()->create();
        $stranger = $this->userWithRole(Role::Student->value);

        $this->actingAs($stranger)
            ->get(route('admin.imports.create', ['import' => 'questions', 'scopeId' => $course->id]))
            ->assertForbidden();
    }

    public function test_valid_rows_import_even_when_others_in_the_file_are_broken(): void
    {
        $course = Course::factory()->create();

        $result = $this->importCsv($course, "type,question,option_a,option_b,correct\n"
            ."mcq_single,Good one,Alpha,Beta,A\n"
            .",,,,\n"
            ."nonsense,Bad type,Alpha,Beta,A\n"
            ."mcq_single,Another good one,Alpha,Beta,B\n");

        $this->assertSame(2, $result['imported'], 'the two valid rows must survive their broken neighbours');
        $this->assertSame(1, $result['skipped']);
    }
}
