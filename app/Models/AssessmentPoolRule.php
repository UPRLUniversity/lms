<?php

namespace App\Models;

use App\Enums\QuestionDifficulty;
use Database\Factories\AssessmentPoolRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One selection rule on a pooled assessment: "draw {count} questions from {category} at
 * {difficulty}". The builder validates each rule against the live pool size; the attempt
 * resolves it into a randomised, frozen question set.
 */
class AssessmentPoolRule extends Model
{
    /** @use HasFactory<AssessmentPoolRuleFactory> */
    use HasFactory;

    protected $fillable = ['assessment_id', 'category_id', 'difficulty', 'count', 'position'];

    protected function casts(): array
    {
        return [
            'difficulty' => QuestionDifficulty::class,
            'count' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<QuestionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }
}
