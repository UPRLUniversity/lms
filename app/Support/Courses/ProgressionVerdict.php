<?php

namespace App\Support\Courses;

use App\Models\Course;
use App\Models\ProgrammePart;
use Illuminate\Support\Collection;

/**
 * The answer to "may this student enrol in this course yet?", and — when the answer is no
 * — everything needed to say why in one sentence.
 *
 * A value object rather than a bare bool so the exception, the toast, the cart error, the
 * catalogue chip and the course page all read from the SAME wording. Five surfaces
 * describing the same rule in five slightly different ways is how a student ends up
 * believing they need something they do not.
 */
class ProgressionVerdict
{
    /**
     * @param  Collection<int, Course>  $outstandingCompulsory
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly ?ProgrammePart $blockingPart = null,
        public readonly Collection $outstandingCompulsory = new Collection,
        public readonly int $creditsEarned = 0,
        public readonly ?int $creditsRequired = null,
    ) {}

    public static function allowed(): self
    {
        return new self(allowed: true);
    }

    /**
     * @param  Collection<int, Course>  $outstandingCompulsory
     */
    public static function blocked(
        ProgrammePart $blockingPart,
        Collection $outstandingCompulsory,
        int $creditsEarned,
        ?int $creditsRequired,
    ): self {
        return new self(
            allowed: false,
            blockingPart: $blockingPart,
            outstandingCompulsory: $outstandingCompulsory,
            creditsEarned: $creditsEarned,
            creditsRequired: $creditsRequired,
        );
    }

    public function isBlocked(): bool
    {
        return ! $this->allowed;
    }

    /**
     * "CPR Part I" — the part standing in the way, named the way a student would see it
     * on the programme page.
     */
    public function blockingPartName(): ?string
    {
        if ($this->blockingPart === null) {
            return null;
        }

        $programme = $this->blockingPart->programme;

        return $programme ? "{$programme->name} · {$this->blockingPart->name}" : $this->blockingPart->name;
    }

    /**
     * The short form for a lock chip or a heading: "Complete CPR · Part I first".
     */
    public function headline(): ?string
    {
        return $this->allowed ? null : 'Complete '.$this->blockingPartName().' first';
    }

    public function creditsOutstanding(): int
    {
        return $this->creditsRequired === null
            ? 0
            : max(0, $this->creditsRequired - $this->creditsEarned);
    }

    public function compulsoryOutstandingCount(): int
    {
        return $this->outstandingCompulsory->count();
    }

    /**
     * The one sentence. Names the part, then only the bars that are actually unmet — a
     * student who has passed every compulsory course and is three credits short should
     * be told about the three credits, not handed both bars and left to work out which
     * one they are failing.
     */
    public function message(): ?string
    {
        if ($this->allowed) {
            return null;
        }

        $reasons = [];

        if (($count = $this->compulsoryOutstandingCount()) > 0) {
            $reasons[] = $count === 1
                ? 'one compulsory course is still to pass'
                : "{$count} compulsory courses are still to pass";
        }

        if ($this->creditsOutstanding() > 0) {
            $reasons[] = "you have earned {$this->creditsEarned} of {$this->creditsRequired} credits";
        }

        $why = match (count($reasons)) {
            0 => '',
            1 => ' — '.$reasons[0].'.',
            default => ' — '.$reasons[0].', and '.$reasons[1].'.',
        };

        return 'You need to complete '.$this->blockingPartName().' first'.($why ?: '.');
    }
}
