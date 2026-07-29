<?php

namespace App\Models;

use App\Enums\CourseRequirement;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The course ↔ programme-part placement row.
 *
 * Modelled explicitly (rather than left as an anonymous pivot) so a placement can be
 * counted and queried on its own — "does this programme still have any courses in
 * it" before allowing a delete — and so credit_load/requirement read back as typed
 * values rather than raw pivot strings.
 */
class CourseProgrammePart extends Model
{
    use AsPivot;

    protected $table = 'course_programme_part';

    protected $fillable = [
        'course_id',
        'programme_part_id',
        'credit_load',
        'requirement',
        'is_primary',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'requirement' => CourseRequirement::class,
            'credit_load' => 'integer',
            'is_primary' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<ProgrammePart, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(ProgrammePart::class, 'programme_part_id');
    }
}
