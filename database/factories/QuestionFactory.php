<?php

namespace Database\Factories;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 *
 * Defaults to a single-answer MCQ; per-type states build a valid payload for every other
 * type. Option/pair/sub ids are stable strings so tests can assert against them.
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * Single-answer items as a prompt PAIRED WITH its own answers. A padded bank question
     * is read by a human during a demo, so the prompt and the options have to belong to
     * each other — a real prompt over generic "Correct / Wrong A" options is worse than
     * useless, because the right answer is then labelled as such on screen. The correct
     * option is always first here; the id order (o1..o4) is what carries correctness, and
     * the taking engine shuffles presentation anyway.
     *
     * @return array<int, array{prompt: string, options: array<int, string>}>
     */
    private static function singleAnswerItems(): array
    {
        return [
            ['prompt' => 'Which of the following best describes the primary goal of public relations?',
                'options' => ['Building mutually beneficial relationships with an organisation\'s publics', 'Selling as many units as possible in a quarter', 'Removing all criticism from the press', 'Publishing content on every available channel']],
            ['prompt' => 'What is the first step in the RACE planning model?',
                'options' => ['Research', 'Action', 'Communication', 'Evaluation']],
            ['prompt' => 'Which document is used to announce news to journalists?',
                'options' => ['A press release', 'An internal memo', 'A staff handbook', 'An annual budget']],
            ['prompt' => 'What does a spokesperson issue in the first hour of a crisis?',
                'options' => ['A holding statement acknowledging the situation', 'A full investigative report', 'A legal counter-claim', 'A scheduled marketing campaign']],
            ['prompt' => 'Which audience should a campaign message be tailored to?',
                'options' => ['The specific public whose behaviour the campaign aims to shift', 'Everyone in the country, equally', 'Only the organisation\'s own staff', 'Whichever audience is cheapest to reach']],
            ['prompt' => 'What quality does the UPRL motto place alongside creativity and competence?',
                'options' => ['Character', 'Capital', 'Compliance', 'Convenience']],
            ['prompt' => 'Which channel is most appropriate for reaching a professional audience?',
                'options' => ['A trade publication or industry newsletter', 'A children\'s television slot', 'A supermarket loyalty card insert', 'A billboard on a rural highway']],
            ['prompt' => 'What makes an organisational message credible to its publics?',
                'options' => ['Consistency between what the organisation says and what it does', 'The size of the advertising budget behind it', 'How frequently the message is repeated', 'The seniority of the person who signs it']],
            ['prompt' => 'Which metric best measures campaign reach?',
                'options' => ['The number of unique people exposed to the message', 'The number of staff who worked on the campaign', 'The total cost of the campaign', 'The number of drafts the copy went through']],
            ['prompt' => 'What is the purpose of a holding statement?',
                'options' => ['To confirm the organisation is aware and responding, before all facts are known', 'To assign blame for the incident immediately', 'To close all media enquiries permanently', 'To announce an unrelated positive news story']],
        ];
    }

    public function definition(): array
    {
        $item = fake()->randomElement(self::singleAnswerItems());

        return [
            'category_id' => null,
            'course_id' => null,
            'created_by' => null,
            'type' => QuestionType::McqSingle->value,
            'difficulty' => QuestionDifficulty::Medium->value,
            'prompt' => '<p>'.$item['prompt'].'</p>',
            'explanation' => '<p>Review the relevant lesson to confirm the correct choice and the reasoning behind it.</p>',
            'points' => 1,
            'payload' => ['options' => self::options($item['options'], [0])],
        ];
    }

    /**
     * Build an option payload from plain strings, marking the given indexes correct.
     *
     * @param  array<int, string>  $texts
     * @param  array<int, int>  $correctIndexes
     * @return array<int, array{id: string, text: string, is_correct: bool}>
     */
    private static function options(array $texts, array $correctIndexes): array
    {
        return array_map(fn (string $text, int $i): array => [
            'id' => 'o'.($i + 1),
            'text' => $text,
            'is_correct' => in_array($i, $correctIndexes, true),
        ], $texts, array_keys($texts));
    }

    public function difficulty(QuestionDifficulty $difficulty): static
    {
        return $this->state(fn () => ['difficulty' => $difficulty->value]);
    }

    public function points(float $points): static
    {
        return $this->state(fn () => ['points' => $points]);
    }

    public function mcqSingle(): static
    {
        // Re-draws prompt AND options together, so a state applied on top of definition()
        // never leaves a real prompt sitting over another question's answers.
        return $this->state(function (): array {
            $item = fake()->randomElement(self::singleAnswerItems());

            return [
                'type' => QuestionType::McqSingle->value,
                'prompt' => '<p>'.$item['prompt'].'</p>',
                'payload' => ['options' => self::options($item['options'], [0])],
            ];
        });
    }

    public function mcqMulti(): static
    {
        return $this->state(function (): array {
            // First two options are the correct pair.
            $items = [
                ['Which of these belong in a SMART campaign objective? (Select all that apply.)',
                    ['Measurable', 'Time-bound', 'Deliberately vague', 'Decided after the campaign ends']],
                ['Which of the following are owned media channels? (Select all that apply.)',
                    ['The organisation\'s website', 'Its e-mail newsletter', 'A paid radio advert', 'A journalist\'s news report']],
                ['Which are legitimate ways to evaluate a PR campaign? (Select all that apply.)',
                    ['Audience awareness surveys before and after', 'Share of voice against competitors', 'The number of press releases written', 'How long the team worked on it']],
                ['Which qualities strengthen a spokesperson in a crisis? (Select all that apply.)',
                    ['Calm, factual delivery', 'Consistency with previously released facts', 'Speculating about causes', 'Blaming an external party early']],
            ];
            [$prompt, $options] = fake()->randomElement($items);

            return [
                'type' => QuestionType::McqMulti->value,
                'prompt' => '<p>'.$prompt.'</p>',
                'payload' => ['options' => self::options($options, [0, 1])],
            ];
        });
    }

    public function trueFalse(bool $answer = true): static
    {
        return $this->state(function () use ($answer): array {
            // The statement has to actually BE true/false as the flag claims, so each
            // truth value draws from its own pool rather than reusing one prompt.
            $statements = $answer
                ? [
                    'A holding statement should be issued before every fact of an incident is known.',
                    'Earned media is coverage gained through PR rather than paid placement.',
                    'The "inverted pyramid" places the most important information first.',
                    'Evaluation is a stage of the RACE model, not an optional extra.',
                ]
                : [
                    'Public relations and advertising are two names for the same discipline.',
                    'An embargo permits journalists to publish immediately on receipt.',
                    'A crisis plan should be written only after a crisis has occurred.',
                    'Reach and engagement measure exactly the same thing.',
                ];

            return [
                'type' => QuestionType::TrueFalse->value,
                'prompt' => '<p>'.fake()->randomElement($statements).'</p>',
                'payload' => [
                    'options' => [
                        ['id' => 'true', 'text' => 'True', 'is_correct' => $answer],
                        ['id' => 'false', 'text' => 'False', 'is_correct' => ! $answer],
                    ],
                ],
            ];
        });
    }

    /**
     * Pass $accepted to pin the answer (tests do); omit it and a coherent
     * prompt-and-answer pair is drawn together.
     *
     * @param  array<int, string>|null  $accepted
     */
    public function fillBlank(?array $accepted = null, bool $caseInsensitive = true): static
    {
        return $this->state(function () use ($accepted, $caseInsensitive): array {
            $items = [
                ['Coverage a brand gains through PR rather than paid placement is called ______ media.', ['earned']],
                ['A request that journalists not publish before a set time is called an ______.', ['embargo']],
                ['The first, brief statement issued while facts are still emerging is a ______ statement.', ['holding']],
                ['In the RACE model, the "R" stands for ______.', ['research']],
            ];
            [$prompt, $answers] = fake()->randomElement($items);

            return array_merge([
                'type' => QuestionType::FillBlank->value,
                'payload' => [
                    'accepted' => $accepted ?? $answers,
                    'case_insensitive' => $caseInsensitive,
                ],
            ], $accepted === null ? ['prompt' => '<p>'.$prompt.'</p>'] : []);
        });
    }

    public function matching(): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::Matching->value,
            'points' => 4,
            'prompt' => '<p>Match each communication channel to what it is best suited for.</p>',
            'payload' => [
                'pairs' => [
                    ['id' => 'p1', 'left' => 'Press release', 'right' => 'Announcing news to journalists'],
                    ['id' => 'p2', 'left' => 'Media interview', 'right' => 'Adding a human, expert voice'],
                    ['id' => 'p3', 'left' => 'Social media', 'right' => 'Real-time two-way engagement'],
                    ['id' => 'p4', 'left' => 'Newsletter', 'right' => 'Nurturing an owned audience'],
                ],
            ],
        ]);
    }

    public function essay(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::Essay->value,
            'points' => 10,
            'payload' => [
                'guidance' => 'Award marks for a clear thesis, supporting evidence and a conclusion.',
            ],
        ]);
    }

    /**
     * A scenario with two objective sub-questions by default; pass withEssay to add a
     * manual sub-question (so the attempt routes to manual grading).
     */
    public function scenario(bool $withEssay = false): static
    {
        return $this->state(function () use ($withEssay) {
            $subs = [
                [
                    'id' => 's1',
                    'type' => QuestionType::McqSingle->value,
                    'prompt' => '<p>Which channel is most appropriate?</p>',
                    'points' => 2,
                    'payload' => [
                        'options' => [
                            ['id' => 'o1', 'text' => 'Press release', 'is_correct' => true],
                            ['id' => 'o2', 'text' => 'Ignore it', 'is_correct' => false],
                        ],
                    ],
                ],
                [
                    'id' => 's2',
                    'type' => QuestionType::TrueFalse->value,
                    'prompt' => '<p>A holding statement should be issued first.</p>',
                    'points' => 1,
                    'payload' => [
                        'options' => [
                            ['id' => 'true', 'text' => 'True', 'is_correct' => true],
                            ['id' => 'false', 'text' => 'False', 'is_correct' => false],
                        ],
                    ],
                ],
            ];

            if ($withEssay) {
                $subs[] = [
                    'id' => 's3',
                    'type' => QuestionType::Essay->value,
                    'prompt' => '<p>Draft the opening line of the holding statement.</p>',
                    'points' => 5,
                    'payload' => ['guidance' => 'Reward calm, factual tone.'],
                ];
            }

            return [
                'type' => QuestionType::Scenario->value,
                'points' => array_sum(array_column($subs, 'points')),
                'prompt' => '<p>A client faces a sudden media crisis. Answer the parts below.</p>',
                'payload' => ['sub_questions' => $subs],
            ];
        });
    }
}
