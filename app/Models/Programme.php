<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\MediaPurpose;
use App\Enums\ProgressionRule;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\ProgrammeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A professional qualification a course can be packaged under — CPR, DPR, the
 * Professional Variant, the Master Class.
 *
 * Sits ALONGSIDE Faculty → Department, not instead of it: a department says who
 * teaches a course, a programme says which qualification it counts toward and what
 * it costs. A course can belong to several programmes, so the join runs through
 * ProgrammePart via a pivot.
 *
 * The three fee columns are the published NIPR schedule. Section 12 resolves a
 * course's price from per_paper_fee; registration_fee and administration_fee are
 * one-off entry charges a student pays once per programme, never per course.
 */
class Programme extends Model
{
    /** @use HasFactory<ProgrammeFactory> */
    use HasFactory, HasMedia, LogsAuditActivity;

    protected $fillable = [
        'name',
        'code',
        'slug',
        'tagline',
        'description',
        'registration_fee',
        'administration_fee',
        'per_paper_fee',
        'position',
        'is_active',
        'progression_rule',
    ];

    /**
     * Mirrors the database default so a newly created (un-refreshed) instance already
     * knows its parts are open, rather than reading null until the model is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'progression_rule' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'description' => RichHtml::class,
            'registration_fee' => 'decimal:2',
            'administration_fee' => 'decimal:2',
            'per_paper_fee' => 'decimal:2',
            'position' => 'integer',
            'is_active' => 'boolean',
            'progression_rule' => ProgressionRule::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return HasMany<ProgrammePart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ProgrammePart::class)->orderBy('position');
    }

    /**
     * Every placement row under this programme, across all its parts. Useful for
     * "does anything still reference me" checks before deletion.
     *
     * @return HasManyThrough<CourseProgrammePart, ProgrammePart, $this>
     */
    public function placements(): HasManyThrough
    {
        return $this->hasManyThrough(CourseProgrammePart::class, ProgrammePart::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Programme>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The display order used everywhere a programme list is rendered.
     *
     * @param  Builder<Programme>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * The one-off cost of entering this programme, paid on a student's first
     * purchase within it (Section 12 reads this).
     */
    public function entryFee(): float
    {
        return (float) $this->registration_fee + (float) $this->administration_fee;
    }

    public function cover(): ?Media
    {
        return $this->firstMediaFor(MediaPurpose::ProgrammeCovers);
    }

    public function coverUrl(): ?string
    {
        return $this->cover()?->url;
    }

    /**
     * Distinct courses placed anywhere in this programme.
     *
     * Deliberately a query, not a relation: courses reach a programme through two
     * hops and a pivot, and a HasManyThrough cannot express that without duplicate
     * rows when a course sits in two parts of the same programme.
     *
     * @return Builder<Course>
     */
    public function coursesQuery(): Builder
    {
        return Course::query()
            ->whereHas('programmeParts', fn ($q) => $q->where('programme_parts.programme_id', $this->id));
    }
}
