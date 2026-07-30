<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Services\Commerce\CartService;
use App\Services\Commerce\PricingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public, guest-visible course catalogue at /courses. Only ever surfaces
 * courses that are BOTH published and publicly visible (the inCatalogue scope);
 * drafts, in-review, archived and enrolled-only courses are never listed, and a
 * direct slug to one 404s for a stranger.
 */
class CatalogueController extends Controller
{
    /** Sort options offered on the catalogue → ordering applied to the query. */
    private const SORTS = ['newest', 'oldest', 'title', 'price-low', 'price-high'];

    public function __construct(
        private readonly PricingService $pricing,
        private readonly CartService $carts,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $faculty = (string) $request->query('faculty', '');
        $department = (string) $request->query('department', '');
        $level = (string) $request->query('level', '');
        $programme = (string) $request->query('programme', '');
        $part = (string) $request->query('part', '');

        $sort = (string) $request->query('sort', 'newest');
        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'newest';
        }

        $courses = Course::query()
            ->inCatalogue()
            // instructors.media, not just instructors: each card renders the lead's
            // avatar, which is itself a media lookup.
            ->with(['department.faculty', 'media', 'instructors.media', 'programmeParts.programme'])
            ->withCount('lessons')
            // The card reads `total_duration` and falls back to a per-course SUM when it
            // is absent — which on a 9-card page was nine extra queries. Aggregate it in
            // the one list query instead.
            ->withSum('lessons as total_duration', 'duration_minutes')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%");
                });
            })
            ->when(in_array($level, CourseLevel::values(), true), fn ($q) => $q->where('level', $level))
            ->when($department !== '', fn ($q) => $q->whereHas('department', fn ($d) => $d->where('slug', $department)))
            ->when($faculty !== '', fn ($q) => $q->whereHas('department.faculty', fn ($f) => $f->where('slug', $faculty)))
            // Programme/part is the second classification axis. Filtering by part alone
            // is meaningless — part slugs ("part-i") are unique only within a programme —
            // so a part narrows an already-chosen programme rather than standing alone.
            ->when($programme !== '', fn ($q) => $q->whereHas(
                'programmeParts.programme',
                fn ($p) => $p->where('programmes.slug', $programme),
            ))
            ->when($programme !== '' && $part !== '', fn ($q) => $q->whereHas(
                'programmeParts',
                fn ($p) => $p->where('programme_parts.slug', $part)
                    ->whereHas('programme', fn ($pr) => $pr->where('programmes.slug', $programme)),
            ))
            // Price is DERIVED (free → override → primary programme's per-paper fee), so
            // sorting by it needs the same rule expressed in SQL. The subquery pulls the
            // primary placement's fee; the CASE below reproduces PricingService's
            // precedence. Kept in one place here rather than spread across the query so
            // it stays comparable with PricingService::priceFor().
            ->when(str_starts_with($sort, 'price-'), fn ($q) => $q
                ->addSelect(['primary_per_paper_fee' => ProgrammePart::query()
                    ->join('course_programme_part as cpp', 'cpp.programme_part_id', '=', 'programme_parts.id')
                    ->join('programmes', 'programmes.id', '=', 'programme_parts.programme_id')
                    ->whereColumn('cpp.course_id', 'courses.id')
                    ->orderByDesc('cpp.is_primary')
                    ->orderBy('cpp.position')
                    ->limit(1)
                    ->select('programmes.per_paper_fee'),
                ])
                ->orderByRaw(
                    '(CASE WHEN courses.is_free THEN 0 ELSE COALESCE(courses.price_override, primary_per_paper_fee, 0) END) '
                    .($sort === 'price-high' ? 'desc' : 'asc'),
                ))
            ->when($sort === 'title', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'oldest', fn ($q) => $q->oldest('published_at'))
            ->when($sort === 'newest', fn ($q) => $q->latest('published_at'))
            ->paginate(9)
            ->withQueryString();

        $data = [
            'courses' => $courses,
            'faculties' => Faculty::query()->with('departments')->orderBy('name')->get(),
            'programmes' => Programme::query()->active()->with('parts')->ordered()->get(),
            'levels' => CourseLevel::cases(),
            'filters' => compact('search', 'faculty', 'department', 'level', 'programme', 'part', 'sort'),
        ];

        // Live filtering: return only the results grid for an AJAX request, the
        // full page otherwise (the same progressive-enhancement pattern as the
        // admin data tables — no full reload when JS is on, still works without it).
        if ($request->ajax() || $request->wantsJson()) {
            return view('catalogue._grid', $data);
        }

        return view('catalogue.index', $data);
    }

    public function show(Request $request, Course $course): View
    {
        // A stranger may only reach a course that's in the public catalogue.
        abort_unless(
            $course->status->isPublished() && $course->visibility->isPublic(),
            404,
        );

        $course->load([
            'department.faculty',
            'instructors.media',
            'programmeParts.programme',
            'modules.lessons' => fn ($q) => $q->orderBy('position'),
        ]);

        // The viewer's own enrolment (if signed in) drives the enrol card's state.
        $user = $request->user();

        return view('catalogue.show', [
            'course' => $course,
            'enrollment' => $user ? $course->enrollmentFor($user) : null,
            'canManageCourse' => $user ? $user->can('viewRoster', $course) : false,
            // Pricing state for the enrol card. Resolved here rather than in Blade so
            // the view has no reason to reach for a service.
            'price' => $this->pricing->priceFor($course),
            'hasPurchased' => $user ? $this->pricing->hasPurchased($user, $course) : false,
            'inCart' => (bool) $this->carts->existing($user)?->has($course),
        ]);
    }
}
