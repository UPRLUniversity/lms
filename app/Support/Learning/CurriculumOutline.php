<?php

namespace App\Support\Learning;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * The student's whole-course outline as an ordered list of CurriculumItems (lessons,
 * assessments and assignments interleaved). Built once per request by LearningService;
 * the player sidebar iterates it, and the sequential gate consults it so any item can
 * sit behind a required one that comes before it.
 */
final class CurriculumOutline
{
    /**
     * @param  Collection<int, CurriculumItem>  $items  in curriculum order
     */
    public function __construct(public readonly Collection $items) {}

    public function isLessonLocked(Lesson $lesson): bool
    {
        return (bool) $this->find('lesson', $lesson->id)?->locked;
    }

    public function isAssessmentLocked(Assessment $assessment): bool
    {
        return (bool) $this->find('assessment', $assessment->id)?->locked;
    }

    public function isAssignmentLocked(Assignment $assignment): bool
    {
        return (bool) $this->find('assignment', $assignment->id)?->locked;
    }

    /**
     * Items belonging to a module, in order (its pre-assessments, lessons,
     * post-assessments, then assignments).
     *
     * @return Collection<int, CurriculumItem>
     */
    public function forModule(int $moduleId): Collection
    {
        return $this->items->filter(fn (CurriculumItem $i) => $i->moduleId === $moduleId)->values();
    }

    /**
     * Standalone (course-level) assessments + assignments, at the end of the outline.
     *
     * @return Collection<int, CurriculumItem>
     */
    public function standalone(): Collection
    {
        return $this->items
            ->filter(fn (CurriculumItem $i) => ! $i->isLesson() && $i->moduleId === null)
            ->values();
    }

    public function hasAssessments(): bool
    {
        return $this->items->contains(fn (CurriculumItem $i) => $i->isAssessment());
    }

    private function find(string $kind, int $id): ?CurriculumItem
    {
        return $this->items->first(fn (CurriculumItem $i) => $i->kind === $kind && $i->id() === $id);
    }
}
