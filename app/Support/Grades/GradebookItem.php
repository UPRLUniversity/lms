<?php

namespace App\Support\Grades;

use App\Models\Assignment;
use App\Models\Assessment;

/**
 * One row of a student's gradebook: a published, required assessment or assignment and
 * its best/latest graded result, if any. `pending` items (no graded result yet — never
 * attempted, or awaiting manual grading) carry no score and are excluded from the
 * course-percentage computation, but still appear in the table.
 */
class GradebookItem
{
    public function __construct(
        public readonly string $kind, // 'assessment' | 'assignment'
        public readonly Assessment|Assignment $model,
        public readonly bool $pending,
        public readonly ?float $pointsEarned = null,
        public readonly ?float $pointsPossible = null,
        public readonly ?float $percent = null,
        public readonly ?string $gradeLabel = null,
        public readonly ?float $gradePoint = null,
        public readonly ?string $badgeColor = null,
    ) {}

    public function title(): string
    {
        return $this->model->title;
    }

    public function isAssessment(): bool
    {
        return $this->kind === 'assessment';
    }

    public function isAssignment(): bool
    {
        return $this->kind === 'assignment';
    }
}
