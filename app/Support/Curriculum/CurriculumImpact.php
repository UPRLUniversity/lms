<?php

namespace App\Support\Curriculum;

/**
 * How much student work hangs off a single curriculum item — the blast radius of
 * editing or deleting it.
 *
 * Counted once by CurriculumImpactService and then passed around, so the delete guard,
 * the builder's confirm dialog and the audit entry all describe the same impact in the
 * same words rather than each re-counting and phrasing it differently.
 */
final readonly class CurriculumImpact
{
    public function __construct(
        public int $learners = 0,
        public int $progressRows = 0,
        public int $attempts = 0,
        public int $submissions = 0,
        public int $grades = 0,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /**
     * Whether any student has touched this item. The one question the delete guard asks —
     * if this is true the item carries academic record and must not be destroyed.
     */
    public function hasStudentData(): bool
    {
        return $this->progressRows > 0
            || $this->attempts > 0
            || $this->submissions > 0
            || $this->grades > 0;
    }

    /**
     * Whether any of that work has been marked. Graded work is the strongest reason to
     * refuse a change, and is called out separately in the refusal copy.
     */
    public function isGraded(): bool
    {
        return $this->grades > 0;
    }

    /**
     * A specific sentence for a human — "14 students have worked on this — 36 lesson
     * completions, 6 attempts and 2 recorded grades."
     *
     * Leads with the DISTINCT learner count, then the artefacts. The two are different
     * numbers on anything bigger than a single lesson (one student leaves a progress row
     * per lesson), and conflating them would overstate the headcount.
     */
    public function summary(): string
    {
        if (! $this->hasStudentData()) {
            return 'No student has worked on this yet.';
        }

        return $this->learnerPhrase().' worked on this — '.$this->artefactPhrase().'.';
    }

    /**
     * Why a destructive change is being refused, in the instructor's terms — and what to
     * do instead, so the refusal is a signpost rather than a dead end.
     */
    public function refusalReason(string $itemLabel): string
    {
        return "Can't delete this {$itemLabel}: "
            .$this->learnerPhrase().' worked on it ('.$this->artefactPhrase().'). '
            .'Deleting it would erase that record — hide it from students instead.';
    }

    /**
     * "14 students have" / "1 student has" — the subject and verb of both sentences above.
     */
    public function learnerPhrase(): string
    {
        return $this->plural($this->learners, 'student has', 'students have');
    }

    /**
     * The work itself: "36 lesson completions, 6 attempts and 2 recorded grades".
     */
    public function artefactPhrase(): string
    {
        $parts = [];

        if ($this->progressRows > 0) {
            $parts[] = $this->plural($this->progressRows, 'lesson completion', 'lesson completions');
        }

        if ($this->attempts > 0) {
            $parts[] = $this->plural($this->attempts, 'attempt', 'attempts');
        }

        if ($this->submissions > 0) {
            $parts[] = $this->plural($this->submissions, 'submission', 'submissions');
        }

        if ($this->grades > 0) {
            $parts[] = $this->plural($this->grades, 'recorded grade', 'recorded grades');
        }

        return $parts === [] ? 'no recorded work' : $this->join($parts);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'learners' => $this->learners,
            'progress_rows' => $this->progressRows,
            'attempts' => $this->attempts,
            'submissions' => $this->submissions,
            'grades' => $this->grades,
        ];
    }

    private function plural(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }

    /**
     * @param  array<int, string>  $clauses
     */
    private function join(array $clauses): string
    {
        if (\count($clauses) === 1) {
            return $clauses[0];
        }

        $last = array_pop($clauses);

        return implode(', ', $clauses).' and '.$last;
    }
}
