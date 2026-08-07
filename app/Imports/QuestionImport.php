<?php

namespace App\Imports;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Services\Assessments\QuestionBankService;
use App\Support\Import\ImportColumn;
use App\Support\Import\ImportDefinition;
use App\Support\Import\ImportRow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Bulk question authoring from a spreadsheet, scoped to one course.
 *
 * The sheet is deliberately flat — one question per row, options in fixed columns —
 * because that is what a subject expert can actually produce in Excel. The nested types
 * (matching, scenario) are NOT importable and say so: expressing a scenario's
 * sub-questions in a flat grid produces a format nobody can fill in correctly, and a
 * half-working import of the hardest type is worse than sending the human to the editor.
 *
 * `correct` names options by letter (A, B, C…), which is how people write answer keys.
 * For multi-select it takes several: "A,C".
 */
class QuestionImport implements ImportDefinition
{
    /** Option columns offered, in order. Five is what fits a printed answer key. */
    private const OPTION_KEYS = ['option_a', 'option_b', 'option_c', 'option_d', 'option_e'];

    public const UNKNOWN_TYPE = 'unknown_type';

    public const UNSUPPORTED_TYPE = 'unsupported_type';

    public const NO_PROMPT = 'no_prompt';

    public const NO_OPTIONS = 'no_options';

    public const NO_CORRECT = 'no_correct';

    public const BAD_CORRECT = 'bad_correct';

    public const MULTI_ON_SINGLE = 'multi_on_single';

    public const BAD_POINTS = 'bad_points';

    public const NO_ACCEPTED = 'no_accepted';

    public const DUPLICATE = 'duplicate';

    /**
     * Prompts already claimed by an earlier row of the CURRENT file. Instance state
     * reset in prepare(), not a static, so a second import in the same queue worker
     * does not inherit the first file's prompts.
     *
     * @var array<string, true>
     */
    private array $seenPrompts = [];

    public function __construct(private readonly QuestionBankService $bank) {}

    public function key(): string
    {
        return 'questions';
    }

    public function title(): string
    {
        return 'Import questions';
    }

    public function intro(): string
    {
        return 'Add many questions to this course\'s bank at once from a spreadsheet.';
    }

    public function noun(): string
    {
        return 'question';
    }

    public function columns(): array
    {
        return [
            ImportColumn::required('type', 'Type', 'mcq_single, mcq_multi, true_false, fill_blank or essay'),
            ImportColumn::required('question', 'Question', 'The prompt the student reads. Plain text is fine.'),
            ImportColumn::make('option_a', 'Option A', hint: 'Multiple-choice answers. Leave blank for other types.'),
            ImportColumn::make('option_b', 'Option B'),
            ImportColumn::make('option_c', 'Option C'),
            ImportColumn::make('option_d', 'Option D'),
            ImportColumn::make('option_e', 'Option E'),
            ImportColumn::make('correct', 'Correct answer', hint: 'MCQ: the letter(s), e.g. "B" or "A,C". True/false: "true" or "false". Fill-blank: the accepted answers, comma-separated.'),
            ImportColumn::make('points', 'Points', hint: 'Defaults to 1.'),
            ImportColumn::make('difficulty', 'Difficulty', hint: 'easy, medium or hard. Defaults to medium.'),
            ImportColumn::make('category', 'Category', hint: 'Topic name. Created automatically if new to this course.'),
            ImportColumn::make('explanation', 'Explanation', hint: 'Shown after the student answers. Optional.'),
        ];
    }

    public function sampleRows(): array
    {
        return [
            [
                'type' => 'mcq_single',
                'question' => 'Which principle is the foundation of two-way symmetrical public relations?',
                'option_a' => 'Mutual understanding between organisation and publics',
                'option_b' => 'One-way dissemination of favourable information',
                'option_c' => 'Paid placement in mass media',
                'option_d' => 'Crisis suppression',
                'correct' => 'A',
                'points' => '2',
                'difficulty' => 'medium',
                'category' => 'PR Theory',
                'explanation' => 'Grunig and Hunt describe the two-way symmetrical model as negotiation toward mutual understanding.',
            ],
            [
                'type' => 'mcq_multi',
                'question' => 'Which of the following are earned media? Select all that apply.',
                'option_a' => 'A newspaper feature secured through a press release',
                'option_b' => 'A paid billboard',
                'option_c' => 'An unsolicited product review by a blogger',
                'option_d' => 'A sponsored social post',
                'correct' => 'A,C',
                'points' => '3',
                'difficulty' => 'hard',
                'category' => 'Media Relations',
            ],
            [
                'type' => 'true_false',
                'question' => 'A press release and a media advisory serve the same purpose.',
                'correct' => 'false',
                'points' => '1',
                'category' => 'Media Relations',
            ],
            [
                'type' => 'fill_blank',
                'question' => 'The UPRL motto is "Creativity, Competence, ______".',
                'correct' => 'Character',
                'points' => '1',
            ],
            [
                'type' => 'essay',
                'question' => 'Evaluate the ethical tensions in representing a client whose conduct you privately disagree with.',
                'points' => '10',
                'explanation' => 'Look for: professional duty, personal conscience, the limits of advocacy, and a reasoned position.',
            ],
        ];
    }

    public function authorize(User $user, ?Model $scope): bool
    {
        return $scope instanceof Course && $user->can('update', $scope);
    }

    public function prepare(Collection $rows, ?Model $scope, User $actor): array
    {
        /** @var Course $course */
        $course = $scope;

        // Every category the file mentions, resolved in one query. Names are matched
        // case-insensitively so "PR Theory" and "pr theory" don't create two topics.
        $names = $rows
            ->map(fn (ImportRow $r) => Str::lower($r->get('category')))
            ->filter()
            ->unique();

        $categories = QuestionCategory::query()
            ->where('course_id', $course->id)
            ->get()
            ->keyBy(fn (QuestionCategory $c) => Str::lower($c->name));

        $this->seenPrompts = [];

        return [
            'categories' => $categories,
            'wantedCategories' => $names,
        ];
    }

    public function inspect(ImportRow $row, array $context, ?Model $scope): void
    {
        if ($row->isBlank()) {
            $row->fail(ImportRow::EMPTY_ROW);

            return;
        }

        $type = $this->resolveType($row->get('type'));

        if (! $type) {
            $row->fail(self::UNKNOWN_TYPE);

            return;
        }

        if (in_array($type, [QuestionType::Matching, QuestionType::Scenario], true)) {
            $row->fail(self::UNSUPPORTED_TYPE);

            return;
        }

        $row->resolve(['type' => $type->shortLabel()]);

        if ($row->get('question') === '') {
            $row->fail(self::NO_PROMPT);

            return;
        }

        // The same prompt twice in one file is nearly always a copy-paste slip, and
        // silently creating two identical bank entries is the expensive kind of quiet.
        $fingerprint = Str::lower(preg_replace('/\s+/', ' ', trim($row->get('question'))) ?? '');
        if (isset($this->seenPrompts[$fingerprint])) {
            $row->fail(self::DUPLICATE);

            return;
        }
        $this->seenPrompts[$fingerprint] = true;

        if ($row->has('points') && ! $this->isPositiveNumber($row->get('points'))) {
            $row->fail(self::BAD_POINTS);

            return;
        }

        match ($type) {
            QuestionType::McqSingle, QuestionType::McqMulti => $this->inspectMcq($row, $type),
            QuestionType::TrueFalse => $this->inspectTrueFalse($row),
            QuestionType::FillBlank => $this->inspectFillBlank($row),
            default => null,   // essay needs nothing beyond a prompt
        };
    }

    public function apply(ImportRow $row, array $context, ?Model $scope, User $actor): bool
    {
        /** @var Course $course */
        $course = $scope;

        $type = $this->resolveType($row->get('type'));

        if (! $type) {
            return false;
        }

        $categoryId = $this->categoryId($row->get('category'), $course, $actor);

        $this->bank->create([
            'course_id' => $course->id,
            'category_id' => $categoryId,
            'type' => $type,
            'difficulty' => $this->resolveDifficulty($row->get('difficulty'))->value,
            // Plain text from a spreadsheet becomes a paragraph; the RichHtml cast
            // sanitises it on save like every other rich field in the app.
            'prompt' => $this->asHtml($row->get('question')),
            'explanation' => $row->has('explanation') ? $this->asHtml($row->get('explanation')) : null,
            'points' => $row->has('points') ? (float) $row->get('points') : 1,
            'payload' => $this->payloadFor($type, $row),
        ], $actor);

        return true;
    }

    public function problemLabel(string $problem): ?string
    {
        return match ($problem) {
            self::UNKNOWN_TYPE => 'Unknown question type',
            self::UNSUPPORTED_TYPE => 'Matching and scenario questions must be built in the editor',
            self::NO_PROMPT => 'No question text',
            self::NO_OPTIONS => 'Needs at least two options',
            self::NO_CORRECT => 'No correct answer given',
            self::BAD_CORRECT => 'Correct answer names an option that is blank',
            self::MULTI_ON_SINGLE => 'Single-answer question has several correct answers',
            self::BAD_POINTS => 'Points must be a positive number',
            self::NO_ACCEPTED => 'No accepted answer given',
            self::DUPLICATE => 'Duplicate question in this file',
            default => null,
        };
    }

    public function returnRoute(?Model $scope): array
    {
        return ['questions.index', ['course' => $scope]];
    }

    /*
    |--------------------------------------------------------------------------
    | Per-type inspection
    |--------------------------------------------------------------------------
    */

    private function inspectMcq(ImportRow $row, QuestionType $type): void
    {
        $options = $this->options($row);

        if (count($options) < 2) {
            $row->fail(self::NO_OPTIONS);

            return;
        }

        $letters = $this->correctLetters($row->get('correct'));

        if ($letters === []) {
            $row->fail(self::NO_CORRECT);

            return;
        }

        // Every named letter must correspond to an option that actually has text.
        foreach ($letters as $letter) {
            if (! isset($options[$letter])) {
                $row->fail(self::BAD_CORRECT);

                return;
            }
        }

        if ($type === QuestionType::McqSingle && count($letters) > 1) {
            $row->fail(self::MULTI_ON_SINGLE);

            return;
        }

        $row->resolve(['answer' => implode(', ', $letters)]);
    }

    private function inspectTrueFalse(ImportRow $row): void
    {
        $answer = Str::lower($row->get('correct'));

        if (! in_array($answer, ['true', 'false', 't', 'f', 'yes', 'no', '1', '0'], true)) {
            $row->fail(self::NO_CORRECT);

            return;
        }

        $row->resolve(['answer' => $this->isTruthy($answer) ? 'True' : 'False']);
    }

    private function inspectFillBlank(ImportRow $row): void
    {
        if ($this->accepted($row) === []) {
            $row->fail(self::NO_ACCEPTED);

            return;
        }

        $row->resolve(['answer' => implode(' / ', $this->accepted($row))]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cell interpretation
    |--------------------------------------------------------------------------
    */

    /**
     * Option letter → text, skipping blanks. Blank-skipping is why "A,C" can be the
     * answer key of a question whose B is empty: the letters address the COLUMN, which
     * is what the human wrote, not a position in a compacted list.
     *
     * @return array<string, string>
     */
    private function options(ImportRow $row): array
    {
        $options = [];

        foreach (self::OPTION_KEYS as $index => $key) {
            $text = $row->get($key);

            if ($text !== '') {
                $options[chr(65 + $index)] = $text;
            }
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function correctLetters(string $value): array
    {
        return collect(preg_split('/[,;\/|]+/', Str::upper($value)) ?: [])
            ->map(fn (string $part) => trim($part))
            ->filter(fn (string $part) => preg_match('/^[A-E]$/', $part) === 1)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function accepted(ImportRow $row): array
    {
        return collect(explode(',', $row->get('correct')))
            ->map(fn (string $a) => trim($a))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function payloadFor(QuestionType $type, ImportRow $row): array
    {
        return match ($type) {
            QuestionType::McqSingle, QuestionType::McqMulti => [
                'options' => $this->optionPayload($row),
            ],
            QuestionType::TrueFalse => [
                'answer' => $this->isTruthy(Str::lower($row->get('correct'))),
            ],
            QuestionType::FillBlank => [
                'accepted' => $this->accepted($row),
                'case_insensitive' => true,
            ],
            QuestionType::Essay => [
                'guidance' => $row->has('explanation') ? $this->asHtml($row->get('explanation')) : null,
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function optionPayload(ImportRow $row): array
    {
        $correct = $this->correctLetters($row->get('correct'));
        $payload = [];

        foreach ($this->options($row) as $letter => $text) {
            $payload[] = [
                'text' => $text,
                'is_correct' => in_array($letter, $correct, true),
            ];
        }

        return $payload;
    }

    /**
     * Find or create the named topic within this course. Case-insensitive so the same
     * topic typed two ways doesn't fragment the bank.
     */
    private function categoryId(string $name, Course $course, User $actor): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $existing = QuestionCategory::query()
            ->where('course_id', $course->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        return ($existing ?? QuestionCategory::create([
            'course_id' => $course->id,
            'created_by' => $actor->id,
            'name' => $name,
        ]))->id;
    }

    private function resolveType(string $value): ?QuestionType
    {
        $key = preg_replace('/[^a-z]/', '', Str::lower($value)) ?? '';

        return match ($key) {
            'mcqsingle', 'mcq', 'multiplechoice', 'single' => QuestionType::McqSingle,
            'mcqmulti', 'multiselect', 'multi', 'multipleanswer' => QuestionType::McqMulti,
            'truefalse', 'tf', 'boolean' => QuestionType::TrueFalse,
            'fillblank', 'fillintheblank', 'shortanswer', 'fill' => QuestionType::FillBlank,
            'essay', 'longanswer', 'written' => QuestionType::Essay,
            'matching', 'match' => QuestionType::Matching,
            'scenario', 'case' => QuestionType::Scenario,
            default => null,
        };
    }

    private function resolveDifficulty(string $value): QuestionDifficulty
    {
        return QuestionDifficulty::tryFrom(Str::lower(trim($value))) ?? QuestionDifficulty::Medium;
    }

    private function isTruthy(string $value): bool
    {
        return in_array($value, ['true', 't', 'yes', '1'], true);
    }

    private function isPositiveNumber(string $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }

    /**
     * Wrap spreadsheet plain text as a paragraph, escaping anything that would otherwise
     * be read as markup. The RichHtml cast sanitises on save regardless; this makes sure
     * a stray "<" in a question reads as a "<" rather than vanishing into a tag.
     */
    private function asHtml(string $text): string
    {
        return '<p>'.e($text).'</p>';
    }
}
