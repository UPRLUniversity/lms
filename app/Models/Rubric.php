<?php

namespace App\Models;

use Database\Factories\RubricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable grading rubric: ordered criteria, each with achievement levels carrying
 * points. Owned by the instructor who built it and attachable to any of their
 * assignments — grading snapshots the selections, so editing a rubric never rewrites
 * an existing grade.
 */
class Rubric extends Model
{
    /** @use HasFactory<RubricFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<RubricCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('position');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * The best possible score: the highest level's points on every criterion.
     */
    public function totalPoints(): float
    {
        $criteria = $this->relationLoaded('criteria') ? $this->criteria : $this->criteria()->get();

        return (float) $criteria->sum(fn (RubricCriterion $c) => $c->maxPoints());
    }
}
