<?php

namespace App\Enums;

/**
 * Where an assessment sits in the course curriculum. A pre/post pair attached to the
 * same module powers the knowledge-gain insight; a standalone exam attaches to the
 * course as a whole and renders at the end of the outline.
 *
 *   pre_module   → before its module's lessons (a diagnostic / "what do you know?")
 *   post_module  → after its module's lessons (the check at the end of a section)
 *   standalone   → a course-level quiz/exam, not tied to a module
 */
enum AssessmentPlacement: string
{
    case PreModule = 'pre_module';
    case PostModule = 'post_module';
    case Standalone = 'standalone';

    public function label(): string
    {
        return match ($this) {
            self::PreModule => 'Pre-module',
            self::PostModule => 'Post-module',
            self::Standalone => 'Standalone',
        };
    }

    /**
     * A short verb-y label for the "add assessment" affordances in the builder.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::PreModule => 'Add pre-module assessment',
            self::PostModule => 'Add post-module assessment',
            self::Standalone => 'Add standalone assessment',
        };
    }

    public function attachesToModule(): bool
    {
        return $this !== self::Standalone;
    }

    /**
     * Whether this placement renders before its module's lessons (vs after).
     */
    public function isBeforeLessons(): bool
    {
        return $this === self::PreModule;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
