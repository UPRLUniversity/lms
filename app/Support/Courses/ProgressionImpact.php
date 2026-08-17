<?php

namespace App\Support\Courses;

use Illuminate\Support\Collection;

/**
 * What switching a programme to `sequential` would have done to the students already in it.
 *
 * The answer to a question an administrator can only usefully ask BEFORE they flip the
 * switch, so it is a hypothetical: every row here is a live enrolment the rule would refuse
 * if it were made today. Nothing is revoked, ever. The gate applies at enrolment time only.
 *
 * A value object because two surfaces ask the same question and must give the same answer:
 * the programme form (which shows it inline when the admin selects the rule) and
 * `progression:audit` (which prints it for a whole estate at once).
 */
class ProgressionImpact
{
    /**
     * @param  Collection<int, array{student: string, email: ?string, course: string, courseTitle: string, status: string, blockedBy: ?string, override: bool}>  $rows
     */
    public function __construct(
        public readonly int $checked,
        public readonly Collection $rows,
    ) {}

    public static function empty(): self
    {
        return new self(checked: 0, rows: new Collection);
    }

    /**
     * Enrolments the rule would refuse. One student blocked in three courses counts three.
     */
    public function blockedCount(): int
    {
        return $this->rows->count();
    }

    /**
     * People, not enrolments. The number an administrator actually cares about, because it
     * is the number of students who would have written in.
     */
    public function studentCount(): int
    {
        return $this->rows->pluck('student')->unique()->count();
    }

    public function isClear(): bool
    {
        return $this->rows->isEmpty();
    }
}
