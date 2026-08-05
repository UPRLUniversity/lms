<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\CourseGradeRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
