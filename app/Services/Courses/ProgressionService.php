<?php

namespace App\Services\Courses;

use App\Enums\CourseRequirement;
use App\Enums\EnrollmentStatus;
use App\Enums\ProgressionRule;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Support\Courses\ProgressionVerdict;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The single answer to "may this student enrol in this course yet?".
 *
 * The rule (docs/plans/prerequisites.md §3) is part-level, not per-course. A student may
 * take a course placed in part P of programme G when:
 *
 *     G is `open`                              → always
 *     OR P is the FIRST part of G              → always (see below)
 *     OR every earlier part of G is CLEARED
 *
 * A part is cleared when every compulsory course in it has been PASSED, and — only where
 * the part states a credit bar — the student has earned that many credits in it.
 *
 * **The first part is always open**, and that is not an oversight. Programme entry fees
 * are only ever charged when the cart already holds a paid course from that programme, so
 * gating the first part on anything would close the commercial funnel entirely: nobody
 * could buy the first course, so nobody could ever pay the entry fee, so nobody could
 * ever start. The entry fee stays enforced in the cart, where money actually moves.
 *
 * A course in NO programme part is never gated — short courses and Master Classes are
 * deliberately standalone. A course in SEVERAL parts passes if ANY placement is unlocked:
 * a student must never be blocked from a course they are legitimately entitled to through
 * a different programme.
 *
 * Nothing here enforces anything. It answers the question; the callers decide what to do
 * with the answer, and three of them deliberately ignore it.
 */
class ProgressionService
{
    /**
     * Per-instance memo of "which courses has this user passed", keyed by user id. The
     * catalogue asks about 40 courses across a dozen parts in one request and every one of
     * them needs the same set; re-deriving it per question would be the N+1 this class
     * exists to avoid.
     *
     * @var array<int, array<int, bool>>
     */
    private array $passedCache = [];

    /*
    |--------------------------------------------------------------------------
    | The question
    |--------------------------------------------------------------------------
    */

    /**
     * May this student enrol in this course?
     */
    public function check(User $user, Course $course): ProgressionVerdict
    {
        return $this->verdictsFor($user, collect([$course]))->get($course->id)
            ?? ProgressionVerdict::allowed();
    }

    /**
     * Verdicts for many courses at once, keyed by course id.
     *
     * Batched for the same reason PricingService batches: the catalogue renders 40 cards
     * and must not make 40 round trips. Every placement, part and passed-course lookup
     * this needs is resolved in a fixed number of queries regardless of how many courses
     * are asked about.
     *
     * `$assumeSequential` evaluates ONE named programme as though its rule were already
     * `sequential`, whatever it is actually set to. It exists for `progression:audit`,
     * which has to answer "who would this block?" before anybody flips the switch — the
     * question is worthless if it can only be asked after the fact. Nothing else should
     * pass it: everywhere real, the stored rule is the rule.
     *
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, ProgressionVerdict>
     */
    public function verdictsFor(User $user, Collection $courses, ?Programme $assumeSequential = null): Collection
    {
        if ($courses->isEmpty()) {
            return collect();
        }

        // Callers hand us anything iterable (a plain collect() from check(), an Eloquent
        // result from the catalogue), so normalise before eager-loading onto the models.
        $courses = EloquentCollection::make($courses->all());
        $courses->loadMissing('programmeParts.programme');

        // Every part of every programme any of these courses touches — needed because
        // "cleared" is a question about the EARLIER parts, not the ones being asked about.
        $programmeIds = $courses
            ->flatMap(fn (Course $c) => $c->programmeParts->pluck('programme_id'))
            ->unique()
            ->values();

        $partsByProgramme = $programmeIds->isEmpty()
            ? collect()
            : ProgrammePart::query()
                ->whereIn('programme_id', $programmeIds)
                ->with(['programme', 'courses'])
                ->orderBy('position')
                ->get()
                ->groupBy('programme_id');

        $cleared = [];   // part id => bool, computed once per part per request

        return $courses->mapWithKeys(function (Course $course) use ($user, $partsByProgramme, &$cleared, $assumeSequential) {
            return [$course->id => $this->verdictFor($user, $course, $partsByProgramme, $cleared, $assumeSequential)];
        });
    }

    /**
     * Where a student stands in every part of a programme, keyed by part id — for the
     * programme page's ladder.
     *
     * Returns an empty collection for a guest or an `open` programme: with nothing to
     * unlock there is no ladder to draw, and drawing one anyway would invent a rule the
     * programme does not have.
     *
     * @return Collection<int, array{part: ProgrammePart, unlocked: bool, cleared: bool, creditsEarned: int, creditBar: ?int, outstanding: Collection<int, Course>}>
     */
    public function partStatesFor(?User $user, Programme $programme): Collection
    {
        if ($user === null || ! $this->ruleOf($programme)->isSequential()) {
            return collect();
        }

        $parts = $programme->relationLoaded('parts')
            ? $programme->parts
            : $programme->parts()->with('courses')->get();

        $parts->loadMissing('courses');

        $states = collect();
        $allEarlierCleared = true;

        foreach ($parts->sortBy('position') as $part) {
            $cleared = $this->isPartCleared($user, $part);

            $states->put($part->id, [
                'part' => $part,
                // The first part is always open, so the flag starts true and only ever
                // closes behind an uncleared part.
                'unlocked' => $allEarlierCleared,
                'cleared' => $cleared,
                'creditsEarned' => $this->creditsEarnedIn($user, $part),
                'creditBar' => $this->creditBarFor($part),
                'outstanding' => $this->compulsoryOutstanding($user, $part),
            ]);

            $allEarlierCleared = $allEarlierCleared && $cleared;
        }

        return $states;
    }

    /*
    |--------------------------------------------------------------------------
    | The two bars
    |--------------------------------------------------------------------------
    */

    /**
     * Courses the student has PASSED within this part.
     *
     * @return Collection<int, Course>
     */
    public function passedCoursesIn(User $user, ProgrammePart $part): Collection
    {
        $passed = $this->passedCourseIds($user);

        return $this->partCourses($part)->filter(fn (Course $c) => isset($passed[$c->id]))->values();
    }

    /**
     * Bar 1 — compulsory courses in this part the student has not passed yet. Empty means
     * the bar is met. Always applies.
     *
     * @return Collection<int, Course>
     */
    public function compulsoryOutstanding(User $user, ProgrammePart $part): Collection
    {
        $passed = $this->passedCourseIds($user);

        return $this->partCourses($part)
            ->filter(fn (Course $c) => $this->requirementOf($c) === CourseRequirement::Compulsory)
            ->reject(fn (Course $c) => isset($passed[$c->id]))
            ->values();
    }

    /**
     * Bar 2 — credits the student has earned in this part.
     *
     * Counts only placements that count toward the published total (everything but a pure
     * elective), mirroring ProgrammePart::creditsCounted(). Counting pure electives here
     * would let a student clear a 24-credit bar with credits that do NOT count toward the
     * 24 the prospectus advertises — and they would arrive at the end of the programme
     * short, having been told all along they were on track.
     */
    public function creditsEarnedIn(User $user, ProgrammePart $part): int
    {
        return (int) $this->passedCoursesIn($user, $part)
            ->filter(fn (Course $c) => $this->requirementOf($c)?->countsTowardTarget() ?? false)
            ->sum(fn (Course $c) => (int) ($c->pivot->credit_load ?? 0));
    }

    /**
     * The credit bar for a part, or null when it states none — most parts do not, and a
     * part with no target is judged on the compulsory bar alone rather than on a number
     * nobody published.
     */
    public function creditBarFor(ProgrammePart $part): ?int
    {
        return $part->unlock_credits ?? $part->credit_target;
    }

    /**
     * Both bars met.
     */
    public function isPartCleared(User $user, ProgrammePart $part): bool
    {
        if ($this->compulsoryOutstanding($user, $part)->isNotEmpty()) {
            return false;
        }

        $bar = $this->creditBarFor($part);

        return $bar === null || $this->creditsEarnedIn($user, $part) >= $bar;
    }

    /*
    |--------------------------------------------------------------------------
    | Passing
    |--------------------------------------------------------------------------
    */

    /**
     * Course ids this student has passed, as a lookup set.
     *
     * Passed = a COMPLETED enrolment whose recorded grade is not a fail. An UNGRADED
     * completion counts as passed: some courses carry no assessment at all, and refusing
     * those would gate progression on data that is never going to arrive.
     *
     * The verdict comes from CourseGradeRecord::isPass(), which reads the scale frozen at
     * completion — so re-cutting a grade boundary later can never retroactively block a
     * student this service has already let through.
     *
     * @return array<int, bool> course id => true
     */
    private function passedCourseIds(User $user): array
    {
        if (isset($this->passedCache[$user->id])) {
            return $this->passedCache[$user->id];
        }

        $completed = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', EnrollmentStatus::Completed->value)
            ->pluck('course_id')
            ->unique();

        if ($completed->isEmpty()) {
            return $this->passedCache[$user->id] = [];
        }

        $failed = CourseGradeRecord::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $completed)
            ->current()
            ->get()
            ->reject(fn (CourseGradeRecord $record) => $record->isPass())
            ->pluck('course_id')
            ->all();

        return $this->passedCache[$user->id] = $completed
            ->reject(fn (int $id) => in_array($id, $failed, true))
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, Collection<int, ProgrammePart>>  $partsByProgramme
     * @param  array<int, bool>  $cleared
     */
    private function verdictFor(User $user, Course $course, Collection $partsByProgramme, array &$cleared, ?Programme $assumeSequential = null): ProgressionVerdict
    {
        $placements = $course->programmeParts;

        // Unplaced: never gated.
        if ($placements->isEmpty()) {
            return ProgressionVerdict::allowed();
        }

        $blockers = [];

        foreach ($placements as $placement) {
            $programme = $placement->programme;

            $enforcing = $programme !== null
                && ($this->ruleOf($programme)->isSequential() || $assumeSequential?->id === $programme->id);

            if (! $enforcing) {
                return ProgressionVerdict::allowed();
            }

            $siblings = $partsByProgramme->get($programme->id, collect());

            $earlier = $siblings->filter(fn (ProgrammePart $p) => $p->position < $placement->position)->values();

            // The first part of a programme is always open.
            if ($earlier->isEmpty()) {
                return ProgressionVerdict::allowed();
            }

            $blocking = $earlier->first(function (ProgrammePart $part) use ($user, &$cleared) {
                return ! ($cleared[$part->id] ??= $this->isPartCleared($user, $part));
            });

            if ($blocking === null) {
                return ProgressionVerdict::allowed();   // this placement is unlocked
            }

            $blockers[] = $blocking;
        }

        return $this->blockedBy($user, $blockers);
    }

    /**
     * Build the verdict from whichever blocking part the student is CLOSEST to clearing.
     *
     * When a course sits in two programmes and both are locked, telling them about the one
     * they have barely started, rather than the one they are two credits from finishing,
     * is the difference between a useful instruction and a discouraging one.
     *
     * @param  array<int, ProgrammePart>  $blockers
     */
    private function blockedBy(User $user, array $blockers): ProgressionVerdict
    {
        $candidates = collect($blockers)
            ->unique('id')
            ->map(function (ProgrammePart $part) use ($user) {
                $bar = $this->creditBarFor($part);
                $earned = $this->creditsEarnedIn($user, $part);

                return [
                    'part' => $part,
                    'outstanding' => $this->compulsoryOutstanding($user, $part),
                    'earned' => $earned,
                    'bar' => $bar,
                    'shortfall' => $bar === null ? 0 : max(0, $bar - $earned),
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => $a['outstanding']->count() <=> $b['outstanding']->count(),
                fn (array $a, array $b) => $a['shortfall'] <=> $b['shortfall'],
            ])
            ->values();

        $best = $candidates->first();

        return ProgressionVerdict::blocked(
            blockingPart: $best['part'],
            outstandingCompulsory: $best['outstanding'],
            creditsEarned: $best['earned'],
            creditsRequired: $best['bar'],
        );
    }

    /**
     * The courses placed in a part, with their pivot. Uses the loaded relation when the
     * caller has eager-loaded it (verdictsFor always does).
     *
     * @return Collection<int, Course>
     */
    private function partCourses(ProgrammePart $part): Collection
    {
        return $part->relationLoaded('courses') ? $part->courses : $part->courses()->get();
    }

    private function requirementOf(Course $course): ?CourseRequirement
    {
        $requirement = $course->pivot->requirement ?? null;

        return $requirement instanceof CourseRequirement
            ? $requirement
            : ($requirement ? CourseRequirement::tryFrom($requirement) : null);
    }

    private function ruleOf(Programme $programme): ProgressionRule
    {
        return $programme->progression_rule instanceof ProgressionRule
            ? $programme->progression_rule
            : (ProgressionRule::tryFrom((string) $programme->progression_rule) ?? ProgressionRule::Open);
    }
}
