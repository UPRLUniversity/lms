<?php

namespace App\Models;

use App\Support\Curriculum\ChangeSignificance;
use Database\Factories\CourseChangeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One recorded change to a course, in the words a student reads (Section 16).
 *
 * Insert-only, like AuditActivity: a course's history of what moved and when is only
 * worth anything if it can't be tidied up afterwards. The audit log records the same
 * events for administrators with full before/after payloads; this is the student- and
 * instructor-facing narrative, deliberately readable rather than forensic.
 */
class CourseChange extends Model
{
    /** @use HasFactory<CourseChangeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'course_id',
        'user_id',
        'subject_type',
        'subject_id',
        'action',
        'summary',
        'note',
        'significance',
    ];

    protected function casts(): array
    {
        return [
            'significance' => ChangeSignificance::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Append-only by construction — the same stance (and the same shape of guard) as
     * AuditActivity.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('Course changes are a record of what happened; they are never edited.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Course changes are a record of what happened; they are never deleted.');
        });
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Whoever made the change. Nullable so a system-driven change still records.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The lesson/assessment/assignment/module the change was made to, when it survived.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The changes worth showing a learner.
     *
     * @param  Builder<CourseChange>  $query
     */
    public function scopeMaterial(Builder $query): void
    {
        $query->where('significance', ChangeSignificance::Material->value);
    }

    /**
     * Changes made since a student joined — they don't need to hear about edits that
     * predate their enrolment, which for them are just how the course has always been.
     *
     * @param  Builder<CourseChange>  $query
     */
    public function scopeSince(Builder $query, ?\DateTimeInterface $moment): void
    {
        if ($moment !== null) {
            $query->where('created_at', '>=', $moment);
        }
    }

    public function isMaterial(): bool
    {
        return $this->significance === ChangeSignificance::Material;
    }
}
