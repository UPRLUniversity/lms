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
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $faculty = (string) $request->query('faculty', '');
        $department = (string) $request->query('department', '');
        $level = (string) $request->query('level', '');

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
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('catalogue.index', [
            'courses' => $courses,
            'faculties' => Faculty::query()->with('departments')->orderBy('name')->get(),
            'levels' => CourseLevel::cases(),
            'filters' => compact('search', 'faculty', 'department', 'level'),
        ]);
    }

    public function show(Course $course): View
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

        return view('catalogue.show', [
            'course' => $course,
        ]);
    }
}
