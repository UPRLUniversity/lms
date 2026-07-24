<?php

namespace App\Support\Grades;

use App\Models\GradeBand;
use App\Models\GradeScale;
use Illuminate\Support\Collection;

/**
 * A student's whole-course gradebook: the item table plus the points-weighted overall
 * result. `provisional` is true while any required item is still pending — the summary
 * then reflects only the graded-so-far items, not a final figure.
 */
class GradebookSummary
{
    /**
     * @param  Collection<int, GradebookItem>  $items
     */
    public function __construct(
        public readonly Collection $items,
        public readonly GradeScale $scale,
        public readonly ?float $percent,
        public readonly ?GradeBand $band,
        public readonly bool $provisional,
    ) {}

    /**
     * Whether the course has any gradable item at all. False ⇒ the empty state, and no
     * completion snapshot is ever produced for this course.
     */
    public function hasItems(): bool
    {
        return $this->items->isNotEmpty();
    }

    /**
     * A final result exists only once every item is graded (not provisional) and the
     * course has at least one gradable item.
     */
    public function isFinal(): bool
    {
        return $this->hasItems() && ! $this->provisional && $this->percent !== null;
    }

    public function gradeLabel(): ?string
    {
        return $this->band?->label;
    }

    public function gradePoint(): ?float
    {
        return $this->band !== null ? (float) $this->band->grade_point : null;
    }

    public function formatted(): ?string
    {
        return $this->percent === null ? null : $this->scale->formatResult($this->percent, $this->band);
    }
}
