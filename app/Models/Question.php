<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\AssessmentStatus;
use App\Enums\MediaPurpose;
use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use App\Models\Concerns\HasMedia;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single reusable question in the bank. The polymorphic `payload` holds the per-type
 * structure (options, accepted answers, matching pairs, nested sub-questions); the typed
 * accessors below are the one place that shape is read, so graders and editors never
 * reach into raw arrays.
 *
 * Soft-deletable: a question used by a *published* assessment is never hard-deleted (the
 * bank blocks it and offers duplicate/version instead) so historical attempts keep their
 * referenced question.
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasMedia, SoftDeletes;

    protected $fillable = [
        'category_id',
        'course_id',
        'created_by',
        'type',
        'difficulty',
        'prompt',
        'explanation',
        'points',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => QuestionDifficulty::class,
            'prompt' => RichHtml::class,
            'explanation' => RichHtml::class,
            'points' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Assessments that reference this question in fixed mode.
     *
     * @return BelongsToMany<Assessment, $this>
     */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_questions')
            ->withPivot(['position', 'points_override'])
            ->withTimestamps();
    }

    public function image(): ?Media
    {
        return $this->firstMediaFor(MediaPurpose::QuestionImages);
    }

    /*
    |--------------------------------------------------------------------------
    | Payload accessors — the single place the per-type shape is read
    |--------------------------------------------------------------------------
    */

    /**
     * Selectable options for the option types (mcq_single, mcq_multi, true_false).
     * Each: ['id' => string, 'text' => string, 'is_correct' => bool].
     *
     * @return array<int, array{id: string, text: string, is_correct: bool}>
     */
    public function options(): array
    {
        return array_map(fn (array $o) => [
            'id' => (string) ($o['id'] ?? ''),
            'text' => (string) ($o['text'] ?? ''),
            'is_correct' => (bool) ($o['is_correct'] ?? false),
        ], $this->payload['options'] ?? []);
    }

    /**
     * The ids of the correct options (grader-side only).
     *
     * @return array<int, string>
     */
    public function correctOptionIds(): array
    {
        return array_values(array_map(
            fn (array $o) => $o['id'],
            array_filter($this->options(), fn (array $o) => $o['is_correct']),
        ));
    }

    /**
     * Accepted answers for a fill-blank question.
     *
     * @return array<int, string>
     */
    public function acceptedAnswers(): array
    {
        return array_values(array_map('strval', $this->payload['accepted'] ?? []));
    }

    public function isCaseInsensitive(): bool
    {
        return (bool) ($this->payload['case_insensitive'] ?? true);
    }

    /**
     * Matching pairs. Each: ['id' => string, 'left' => string, 'right' => string].
     *
     * @return array<int, array{id: string, left: string, right: string}>
     */
    public function pairs(): array
    {
        return array_map(fn (array $p) => [
            'id' => (string) ($p['id'] ?? ''),
            'left' => (string) ($p['left'] ?? ''),
            'right' => (string) ($p['right'] ?? ''),
        ], $this->payload['pairs'] ?? []);
    }

    /**
     * Grader-only model answer / guidance for an essay question.
     */
    public function essayGuidance(): ?string
    {
        return $this->payload['guidance'] ?? null;
    }

    /**
     * A scenario's nested sub-questions, each a lightweight question array:
     * ['id', 'type' (QuestionType value), 'prompt', 'points', 'payload', 'explanation'].
     *
     * @return array<int, array<string, mixed>>
     */
    public function subQuestions(): array
    {
        return array_values($this->payload['sub_questions'] ?? []);
    }

    /**
     * Build a transient Question instance from a scenario sub-question array, so the same
     * graders/accessors work on nested sub-questions without persisting them.
     */
    public function makeSubQuestion(array $sub): self
    {
        return (new self)->forceFill([
            'id' => $sub['id'] ?? null,
            'type' => $sub['type'] ?? null,
            'prompt' => $sub['prompt'] ?? '',
            'points' => $sub['points'] ?? 0,
            'payload' => $sub['payload'] ?? [],
            'explanation' => $sub['explanation'] ?? null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Grading helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Whether this question needs a human grader: an essay always; a scenario when any
     * of its sub-questions is an essay.
     */
    public function requiresManualGrading(): bool
    {
        if ($this->type === QuestionType::Essay) {
            return true;
        }

        if ($this->type === QuestionType::Scenario) {
            foreach ($this->subQuestions() as $sub) {
                if (($sub['type'] ?? null) === QuestionType::Essay->value) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether this question is referenced by at least one *published* assessment — the
     * guard that blocks a destructive delete in the bank.
     */
    public function usedByPublishedAssessment(): bool
    {
        return $this->assessments()
            ->where('status', AssessmentStatus::Published->value)
            ->exists();
    }
}
