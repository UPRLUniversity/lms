<?php

namespace App\Support\Learning;

use App\Models\Assessment;
use App\Models\Lesson;

/**
 * One entry in the unified learning outline — a lesson or an assessment — carrying the
 * derived facts the player sidebar and the sequential gate need: whether the student has
 * completed it, whether it's locked, and whether it blocks progression.
 */
final class CurriculumItem
{
    public function __construct(
        public readonly string $kind,        // 'lesson' | 'assessment'
        public readonly Lesson|Assessment $model,
        public readonly bool $completed,
        public readonly bool $locked,
        public readonly bool $required,
        public readonly ?int $moduleId,
        public readonly ?string $placement = null, // assessments: pre_module | post_module | standalone
    ) {}

    public function isLesson(): bool
    {
        return $this->kind === 'lesson';
    }

    public function isAssessment(): bool
    {
        return $this->kind === 'assessment';
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
