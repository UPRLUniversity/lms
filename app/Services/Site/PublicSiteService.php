<?php

namespace App\Services\Site;

use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Everything the public, signed-out site reads: the homepage's figures, its featured
 * courses, and the programme cards shared by the homepage grid and /programmes.
 *
 * Read-only by design — nothing here writes, so a stranger browsing the site never
 * mutates a row. Every number is a real query against live data; none is hard-coded.
 */
class PublicSiteService
{
    /**
     * Short enough that a newly published course shows up on the homepage while the
     * human is still clicking around, long enough that a burst of anonymous traffic
     * doesn't re-sweep four tables per hit.
     */
    private const CACHE_TTL = 300; // 5 minutes

    public const STATS_CACHE_KEY = 'public:site:stats';

    /**
     * The homepage stats band.
     *
     * One database round trip, not four count()s: each figure is a correlated
     * subquery in a single SELECT. The band is decorative-but-truthful, so it is
     * memoised — a stale-by-five-minutes learner count is fine, four full table
     * sweeps on every anonymous hit is not.
     *
     * @return array{courses: int, programmes: int, instructors: int, learners: int}
     */
    public function stats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, self::CACHE_TTL, function (): array {
            $row = DB::query()
                ->selectSub(
                    Course::query()->inCatalogue()->selectRaw('count(*)'),
                    'courses',
                )
                ->selectSub(
                    Programme::query()->active()->selectRaw('count(*)'),
                    'programmes',
                )
                ->selectSub(
                    // whereHas on the roles relation rather than spatie's role() scope:
                    // that scope THROWS when the named role does not exist, and a public
                    // homepage must not 500 on an install whose roles are not seeded yet.
                    User::query()
                        ->where('is_active', true)
                        ->whereHas('roles', fn ($q) => $q->where('name', Role::Instructor->value))
                        ->selectRaw('count(*)'),
                    'instructors',
                )
                ->selectSub(
                    // Distinct people, not enrolment rows: one student on six papers is
                    // one learner. Only seats that were actually taken up count —
                    // pending and rejected applications are not learners.
                    Enrollment::query()
                        ->whereIn('status', [
                            EnrollmentStatus::Active->value,
                            EnrollmentStatus::Completed->value,
                        ])
                        ->selectRaw('count(distinct user_id)'),
                    'learners',
                )
                ->first();

            return [
                'courses' => (int) ($row->courses ?? 0),
                'programmes' => (int) ($row->programmes ?? 0),
                'instructors' => (int) ($row->instructors ?? 0),
                'learners' => (int) ($row->learners ?? 0),
            ];
        });
    }

    /**
     * The homepage's course rail: the most-enrolled catalogue courses, newest first
     * within a tie, so a brand-new platform still shows something.
     *
     * Deliberately NOT cached — the cards carry a price and an in-cart state, and a
     * five-minute-old price is a support ticket. The eager loads mirror
     * CatalogueController's exactly, because the same <x-courses.catalogue-card>
     * renders them (cover media, lead instructor avatar, programme badges, lesson
     * count and total duration all come from this one query).
     *
     * @return EloquentCollection<int, Course>
     */
    public function featuredCourses(int $limit = 6): EloquentCollection
    {
        return Course::query()
            ->inCatalogue()
            ->with(['department', 'media', 'instructors.media', 'programmeParts.programme'])
            ->withCount('lessons')
            ->withSum('lessons as total_duration', 'duration_minutes')
            ->withCount(['enrollments as learner_count' => fn ($q) => $q->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Completed->value,
            ])])
            ->orderByDesc('learner_count')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Active programmes for the homepage grid and /programmes, each carrying how many
     * catalogue-visible courses sit under it.
     *
     * The counts come from one grouped query rather than a count per card, and count
     * DISTINCT courses: a paper placed in two parts of the same programme is one
     * course on offer, not two.
     *
     * @return EloquentCollection<int, Programme>
     */
    public function programmes(): EloquentCollection
    {
        $programmes = Programme::query()->active()->with('parts')->ordered()->get();

        if ($programmes->isEmpty()) {
            return $programmes;
        }

        $counts = DB::table('course_programme_part as cpp')
            ->join('programme_parts as pp', 'pp.id', '=', 'cpp.programme_part_id')
            ->join('courses as c', 'c.id', '=', 'cpp.course_id')
            ->whereIn('pp.programme_id', $programmes->modelKeys())
            ->where('c.status', CourseStatus::Published->value)
            ->where('c.visibility', CourseVisibility::PublicCatalogue->value)
            ->groupBy('pp.programme_id')
            ->selectRaw('pp.programme_id as programme_id, count(distinct cpp.course_id) as aggregate')
            ->pluck('aggregate', 'programme_id');

        foreach ($programmes as $programme) {
            $programme->setAttribute('catalogue_courses_count', (int) $counts->get($programme->id, 0));
        }

        return $programmes;
    }

    /**
     * A single programme with its parts and, under each part, only the courses a
     * stranger is allowed to see.
     *
     * Restricting to inCatalogue() is the whole point: a programme page must not
     * become a way to enumerate drafts. The credit sums are therefore computed from
     * the SAME filtered rows the page renders (ProgrammePart::creditsCounted accepts
     * the collection), so the total always reconciles with the visible list rather
     * than quietly including a paper nobody can see.
     */
    public function programmeCurriculum(Programme $programme): Programme
    {
        return $programme->load([
            'parts.courses' => fn ($query) => $query
                ->inCatalogue()
                // programmeParts.programme is loaded because each row prints a price, and
                // PricingService resolves that through the course's PRIMARY placement —
                // which may be a different programme than the one being viewed.
                ->with(['media', 'programmeParts.programme']),
        ]);
    }

    /**
     * Drop the memoised stats band. Called from tests; also the hook an admin action
     * would use if the figures ever need to move immediately.
     */
    public function forgetStats(): void
    {
        Cache::forget(self::STATS_CACHE_KEY);
    }
}
