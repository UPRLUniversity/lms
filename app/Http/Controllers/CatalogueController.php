<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\Faculty;
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
    private const SORTS = ['newest', 'oldest', 'title'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $faculty = (string) $request->query('faculty', '');
        $department = (string) $request->query('department', '');
        $level = (string) $request->query('level', '');

        $sort = (string) $request->query('sort', 'newest');
        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'newest';
        }

        $courses = Course::query()
            ->inCatalogue()
            ->with(['department.faculty', 'media', 'instructors'])
            ->withCount('lessons')
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
            ->when($sort === 'title', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'oldest', fn ($q) => $q->oldest('published_at'))
            ->when($sort === 'newest', fn ($q) => $q->latest('published_at'))
            ->paginate(9)
            ->withQueryString();

        $data = [
            'courses' => $courses,
            'faculties' => Faculty::query()->with('departments')->orderBy('name')->get(),
            'levels' => CourseLevel::cases(),
            'filters' => compact('search', 'faculty', 'department', 'level', 'sort'),
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
            'modules.lessons' => fn ($q) => $q->orderBy('position'),
        ]);

        // The viewer's own enrolment (if signed in) drives the enrol card's state.
        $user = $request->user();

        return view('catalogue.show', [
            'course' => $course,
            'enrollment' => $user ? $course->enrollmentFor($user) : null,
            'canManageCourse' => $user ? $user->can('viewRoster', $course) : false,
        ]);
    }
}
