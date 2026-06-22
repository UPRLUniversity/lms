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

    public function definition(): array
    {
        return [
            'category_id' => null,
            'course_id' => null,
            'created_by' => null,
            'type' => QuestionType::McqSingle->value,
            'difficulty' => QuestionDifficulty::Medium->value,
            'prompt' => '<p>'.fake()->sentence().'?</p>',
            'explanation' => '<p>'.fake()->sentence().'</p>',
            'points' => 1,
            'payload' => [
                'options' => [
                    ['id' => 'o1', 'text' => 'Correct option', 'is_correct' => true],
                    ['id' => 'o2', 'text' => 'Wrong option A', 'is_correct' => false],
                    ['id' => 'o3', 'text' => 'Wrong option B', 'is_correct' => false],
                    ['id' => 'o4', 'text' => 'Wrong option C', 'is_correct' => false],
                ],
            ],
        ];
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
        return $this->state(fn () => [
            'type' => QuestionType::McqSingle->value,
            'payload' => [
                'options' => [
                    ['id' => 'o1', 'text' => 'Correct', 'is_correct' => true],
                    ['id' => 'o2', 'text' => 'Wrong A', 'is_correct' => false],
                    ['id' => 'o3', 'text' => 'Wrong B', 'is_correct' => false],
                ],
            ],
        ]);
    }

    public function mcqMulti(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::McqMulti->value,
            'payload' => [
                'options' => [
                    ['id' => 'o1', 'text' => 'Correct A', 'is_correct' => true],
                    ['id' => 'o2', 'text' => 'Correct B', 'is_correct' => true],
                    ['id' => 'o3', 'text' => 'Wrong A', 'is_correct' => false],
                    ['id' => 'o4', 'text' => 'Wrong B', 'is_correct' => false],
                ],
            ],
        ]);
    }

    public function trueFalse(bool $answer = true): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::TrueFalse->value,
            'payload' => [
                'options' => [
                    ['id' => 'true', 'text' => 'True', 'is_correct' => $answer],
                    ['id' => 'false', 'text' => 'False', 'is_correct' => ! $answer],
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $accepted
     */
    public function fillBlank(array $accepted = ['Paris'], bool $caseInsensitive = true): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::FillBlank->value,
            'payload' => [
                'accepted' => $accepted,
                'case_insensitive' => $caseInsensitive,
            ],
        ]);
    }

    public function matching(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::Matching->value,
            'points' => 4,
            'payload' => [
                'pairs' => [
                    ['id' => 'p1', 'left' => 'Nigeria', 'right' => 'Abuja'],
                    ['id' => 'p2', 'left' => 'Ghana', 'right' => 'Accra'],
                    ['id' => 'p3', 'left' => 'Kenya', 'right' => 'Nairobi'],
                    ['id' => 'p4', 'left' => 'Egypt', 'right' => 'Cairo'],
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
