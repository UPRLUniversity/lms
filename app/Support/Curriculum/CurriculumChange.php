<?php

namespace App\Support\Curriculum;

use Illuminate\Database\Eloquent\Model;

/**
 * One described change to a course, ready to be recorded and (if material) announced.
 *
 * The summary is written once, here, in the words a student will read — so the change
 * history, the notification and the audit entry can never describe the same edit three
 * different ways.
 */
final readonly class CurriculumChange
{
    public function __construct(
        public string $action,
        public string $summary,
        public ChangeSignificance $significance,
        public ?Model $subject = null,
    ) {}

    public static function material(string $action, string $summary, ?Model $subject = null): self
    {
        return new self($action, $summary, ChangeSignificance::Material, $subject);
    }

    public static function cosmetic(string $action, string $summary, ?Model $subject = null): self
    {
        return new self($action, $summary, ChangeSignificance::Cosmetic, $subject);
    }

    public function isMaterial(): bool
    {
        return $this->significance->isMaterial();
    }
}
