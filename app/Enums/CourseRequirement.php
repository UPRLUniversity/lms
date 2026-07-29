<?php

namespace App\Enums;

/**
 * How a course counts toward a programme part — the "STATUS" column of the published
 * NIPR curriculum. It is a property of the PLACEMENT, not of the course, so it lives
 * on the course_programme_part pivot: the same paper can be compulsory in one
 * programme and elective in another.
 */
enum CourseRequirement: string
{
    case Compulsory = 'compulsory';
    case RequiredElective = 'required_elective';
    case Elective = 'elective';
    case Gns = 'gns';

    public function label(): string
    {
        return match ($this) {
            self::Compulsory => 'Compulsory',
            self::RequiredElective => 'Required elective',
            self::Elective => 'Elective',
            self::Gns => 'General studies',
        };
    }

    /**
     * A short form for tight spaces (catalogue cards, table cells).
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Compulsory => 'Compulsory',
            self::RequiredElective => 'Req. elective',
            self::Elective => 'Elective',
            self::Gns => 'GNS',
        };
    }

    /**
     * Variant passed to <x-ui.badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Compulsory => 'crimson',
            self::RequiredElective => 'gold',
            self::Elective => 'neutral',
            self::Gns => 'success',
        };
    }

    /**
     * Whether this placement counts toward a part's stated credit target.
     *
     * Everything except a pure elective does. Derived from the published curriculum
     * rather than assumed:
     *
     *   CPR Part I lists 28 credits but is printed as "Total 24". The 4-credit
     *   difference is exactly CPR 118 (2) + CPR 119 (2) — its only two pure
     *   electives. The two GNS papers (2 + 2) are inside the stated 24, so general
     *   studies counts.
     *
     *   DPR Part I is printed as "Total 21" and holds six 3-credit compulsory papers
     *   plus one 3-credit required elective: 18 + 3 = 21. Required electives count.
     *
     * A pure elective is the student's free choice on top of the required load, which
     * is why it sits outside the figure the prospectus advertises.
     */
    public function countsTowardTarget(): bool
    {
        return $this !== self::Elective;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
