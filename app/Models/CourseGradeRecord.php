<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\CourseGradeRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The immutable snapshot of a student's final course grade, written once when their
 * enrollment reaches Completed. `scale_snapshot` freezes the whole scale as applied, so
 * editing or archiving the scale afterwards never rewrites a recorded grade. A re-issue
 * (admin "recompute") never edits this row — it supersedes it with a new version.
 */
class CourseGradeRecord extends Model
{
    /** @use HasFactory<CourseGradeRecordFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'user_id',
        'course_id',
        'version',
        'superseded_at',
        'final_percent',
        'grade_label',
        'grade_point',
        'scale_snapshot',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'superseded_at' => 'datetime',
            'final_percent' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'scale_snapshot' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @param  Builder<CourseGradeRecord>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    /**
     * Did this student pass — as judged by the scale they were actually measured under.
     *
     * Read from the FROZEN snapshot, never the live scale. Re-cutting a grade boundary in
     * 2027 must not retroactively fail a student who graduated in 2026, and progression
     * must never start refusing someone it has already let through.
     *
     * Records written before this column existed carry no `is_pass` in their snapshot; for
     * those the verdict falls back to `grade_point > 0`, the same derivation the migration
     * used to backfill the live bands — so a historical record reads the same way whether
     * its snapshot was stamped before or after this section.
     */
    public function isPass(): bool
    {
        $band = collect($this->scale_snapshot['bands'] ?? [])
            ->first(fn (array $b) => isset($b['label']) && Str::lower((string) $b['label']) === Str::lower((string) $this->grade_label));

        if ($band !== null && array_key_exists('is_pass', $band)) {
            return (bool) $band['is_pass'];
        }

        return (float) $this->grade_point > 0;
    }

    public function outcomeLabel(): string
    {
        return $this->isPass() ? 'Pass' : 'Fail';
    }

    /**
     * The scale's display settings, straight from the frozen snapshot — never the live
     * scale, which may since have changed or been archived.
     */
    public function formatResult(): string
    {
        $snapshot = $this->scale_snapshot;
        $parts = [round((float) $this->final_percent).'%', $this->grade_label];

        $mode = $snapshot['display_mode'] ?? 'both';
        $point = number_format((float) $this->grade_point, 1);
        $limit = number_format((float) ($snapshot['scale_limit'] ?? 0), 1);
        $separator = $snapshot['separator'] ?? '/';
        $showLimit = $snapshot['show_scale_limit'] ?? true;

        if ($mode === 'points') {
            $parts = [round((float) $this->final_percent).'%'];
        }

        if ($mode !== 'letter') {
            $parts[] = $showLimit ? $point.$separator.$limit : $point;
        }

        return implode(' · ', $parts);
    }
}
