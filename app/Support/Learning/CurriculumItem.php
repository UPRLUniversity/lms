<?php

namespace App\Support\Learning;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Lesson;

/**
 * One entry in the unified learning outline — a lesson, an assessment or an assignment —
 * carrying the derived facts the player sidebar and the sequential gate need: whether the
 * student has completed it, whether it's locked, and whether it blocks progression.
 */
final class CurriculumItem
{
    public function __construct(
        public readonly string $kind,        // 'lesson' | 'assessment' | 'assignment'
        public readonly Lesson|Assessment|Assignment $model,
        public readonly bool $completed,
        public readonly bool $locked,
        public readonly bool $required,
        public readonly ?int $moduleId,
        public readonly ?string $placement = null, // assessments: pre_module | post_module | standalone
        // Assessments/assignments only: a short "where things stand" label for the
        // sidebar (e.g. "Not passed · 56% · 1 attempt left", "Awaiting grading") and the
        // brand tone to render it in. Null when there's nothing yet to report (not
        // started) or the item is already complete (the checkmark already says enough).
        public readonly ?string $statusLabel = null,
        public readonly ?string $statusTone = null, // success | gold | crimson | neutral
    ) {}

    public function isLesson(): bool
    {
        return $this->kind === 'lesson';
    }

    public function isAssessment(): bool
    {
        return $this->kind === 'assessment';
    }

    public function isAssignment(): bool
    {
        return $this->kind === 'assignment';
    }

    public function id(): int
    {
        return $this->model->id;
    }

    public function title(): string
    {
        return $this->model->title;
    }
}
