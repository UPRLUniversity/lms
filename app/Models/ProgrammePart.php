<?php

namespace App\Models;

use App\Enums\CourseRequirement;
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\ProgrammePartFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A stage within a programme — "Part I", "Part II". The unit the published
 * curriculum states a credit total against, and the grouping the catalogue and the
 * programme landing page render courses under.
 */
class ProgrammePart extends Model
{
    /** @use HasFactory<ProgrammePartFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'programme_id',
        'name',
        'slug',
        'description',
        'credit_target',
        'unlock_credits',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'credit_target' => 'integer',
            'unlock_credits' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * NOT the route key. Part slugs are unique per programme, not globally, so a
     * bare {part} binding would be ambiguous — parts are always addressed through
     * their programme (scoped bindings) or by id in admin forms.
     */

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_programme_part')
            ->using(CourseProgrammePart::class)
            ->withPivot(['credit_load', 'requirement', 'is_primary', 'position'])
            ->withTimestamps()
            ->orderBy('course_programme_part.position');
    }

    /*
    |--------------------------------------------------------------------------
    | Credit accounting
    |--------------------------------------------------------------------------
    */

    /**
     * Credits actually listed against this part — every placement, elective included.
     *
     * @param  Collection<int, Course>|null  $courses  pass a loaded relation to avoid a query
     */
    public function creditsListed(?Collection $courses = null): int
    {
        return (int) $this->creditRows($courses)->sum(fn (array $row) => $row['credit']);
    }

    /**
     * Credits that count toward the published total: compulsory + required elective.
     *
     * This is the figure that should match credit_target. Pure electives and GNS are
     * excluded — see CourseRequirement::countsTowardTarget() for why CPR Part I is
     * printed as 24 while its courses list 28.
     *
     * @param  Collection<int, Course>|null  $courses
     */
    public function creditsCounted(?Collection $courses = null): int
    {
        return (int) $this->creditRows($courses)
            ->filter(fn (array $row) => $row['requirement']?->countsTowardTarget() ?? false)
            ->sum(fn (array $row) => $row['credit']);
    }

    /**
     * Whether the counted credits reconcile with the stated target. Null when the part
     * states no target, so the UI can stay silent rather than assert a false mismatch.
     */
    public function creditsReconcile(?Collection $courses = null): ?bool
    {
        if ($this->credit_target === null) {
            return null;
        }

        return $this->creditsCounted($courses) === $this->credit_target;
    }

    /**
     * @param  Collection<int, Course>|null  $courses
     * @return \Illuminate\Support\Collection<int, array{credit: int, requirement: ?CourseRequirement}>
     */
    private function creditRows(?Collection $courses = null): \Illuminate\Support\Collection
    {
        $courses ??= $this->relationLoaded('courses') ? $this->courses : $this->courses()->get();

        return $courses->map(function (Course $course) {
            $requirement = $course->pivot->requirement;

            return [
                'credit' => (int) ($course->pivot->credit_load ?? 0),
                // The CourseProgrammePart pivot casts this to the enum, but a caller may
                // hand us a collection loaded through a plain pivot, so accept either.
                'requirement' => $requirement instanceof CourseRequirement
                    ? $requirement
                    : ($requirement ? CourseRequirement::tryFrom($requirement) : null),
            ];
        });
    }
}
