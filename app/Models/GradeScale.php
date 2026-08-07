<?php

namespace App\Models;

use App\Enums\GradeDisplayMode;
use App\Enums\GradeScaleStatus;
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable letter-grade / grade-point layer over the scoring engine (Attempt.score,
 * Grade.points_total). Purely presentation + aggregation: it never rewrites a score.
 * Exactly one scale is the system default at a time; a course may override it.
 */
class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'name',
        'scale_limit',
        'is_default',
        'display_mode',
        'show_scale_limit',
        'separator',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scale_limit' => 'decimal:2',
            'is_default' => 'boolean',
            'display_mode' => GradeDisplayMode::class,
            'show_scale_limit' => 'boolean',
            'status' => GradeScaleStatus::class,
        ];
    }

    /**
     * @return HasMany<GradeBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderBy('position');
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * @param  Builder<GradeScale>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', GradeScaleStatus::Active->value);
    }

    /**
     * @param  Builder<GradeScale>  $query
     */
    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    public function isArchived(): bool
    {
        return $this->status === GradeScaleStatus::Archived;
    }

    /**
     * The band a given percentage falls into. Scores are rounded to the nearest whole
     * percent before mapping (bands are defined on integers 0–100), so a fractional
     * points-weighted result like 59.5% resolves the same way a human reading "60%"
     * would expect, rather than being pinned to the band below by truncation.
     */
    public function bandFor(float $percent): ?GradeBand
    {
        $rounded = max(0, min(100, (int) round($percent)));

        $bands = $this->relationLoaded('bands') ? $this->bands : $this->bands()->get();

        return $bands->first(fn (GradeBand $band) => $band->covers($rounded));
    }

    /**
     * The lowest percentage that still passes — the scale's pass mark, derived from the
     * lowest passing band rather than stored, so it can never contradict the bands.
     * Null only for a scale with no passing band, which GradeBandValidator refuses.
     */
    public function passMark(): ?int
    {
        $bands = $this->relationLoaded('bands') ? $this->bands : $this->bands()->get();

        return $bands->where('is_pass', true)->min('min_percent');
    }

    /**
     * The whole scale, frozen for a completion snapshot: never re-derived from the live
     * scale afterwards, so editing bands later can't rewrite a recorded grade.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        $bands = $this->relationLoaded('bands') ? $this->bands : $this->bands()->get();

        return [
            'grade_scale_id' => $this->id,
            'name' => $this->name,
            'scale_limit' => (float) $this->scale_limit,
            'display_mode' => $this->display_mode->value,
            'show_scale_limit' => $this->show_scale_limit,
            'separator' => $this->separator,
            'bands' => $bands->map(fn (GradeBand $b) => $b->toSnapshot())->values()->all(),
        ];
    }

    /**
     * Render a percentage per this scale's display settings, e.g. "82% · A · 4.0/5.0".
     */
    public function formatResult(float $percent, ?GradeBand $band = null): string
    {
        $band ??= $this->bandFor($percent);
        $parts = [round($percent).'%'];

        if ($band) {
            if ($this->display_mode->showsLetter()) {
                $parts[] = $band->label;
            }
            if ($this->display_mode->showsPoints()) {
                $point = number_format((float) $band->grade_point, 1);
                $parts[] = $this->show_scale_limit
                    ? $point.$this->separator.number_format((float) $this->scale_limit, 1)
                    : $point;
            }
        }

        return implode(' · ', $parts);
    }
}
