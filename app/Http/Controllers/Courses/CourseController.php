<?php

namespace App\Http\Controllers\Courses;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\StoreCourseRequest;
use App\Http\Requests\Courses\UpdateCourseSettingsRequest;
use App\Models\Course;
use App\Models\Department;
use App\Services\Courses\CoursePublishingService;
use App\Services\Courses\EnrollmentService;
use App\Services\Media\MediaUploadService;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    /**
     * The instructor's course list (admins see every course). Card grid with cover,
     * status, lesson count and quick actions; an inviting empty state otherwise.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Course::class);

        $user = $request->user();
        // Admins and super-admins govern every course; instructors see only theirs.
        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        $status = (string) $request->query('status', '');

        $courses = Course::query()
            ->with(['department.faculty', 'media'])
            ->withCount('lessons')
            ->unless($isAdmin, fn ($q) => $q->forInstructor($user))
            ->when(in_array($status, CourseStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('courses.index', [
            'courses' => $courses,
            'statuses' => CourseStatus::cases(),
            'activeStatus' => $status,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Course::class);

        return view('courses.create', [
            'levels' => CourseLevel::cases(),
            'departments' => Department::query()->with('faculty')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $course = Course::create([
            'title' => $data['title'],
            'slug' => Slug::unique(Course::class, $data['title']),
            'code' => $data['code'],
            'department_id' => $data['department_id'],
            'level' => $data['level'],
            'summary' => $data['summary'] ?? null,
            'status' => CourseStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        // The creator is the course's lead instructor by default.
        $course->instructors()->attach($request->user()->id, ['is_lead' => true]);

        return redirect()
            ->route('courses.edit', $course)
            ->with('status', "“{$course->title}” was created. Now build it out.");
    }

    /**
     * The course builder: Settings + Curriculum tabs. Doubles as the admin review
     * screen (admins reach any course; the publish/return panel appears for them).
     */
    public function edit(Course $course): View
    {
        $this->authorize('view', $course);

        $course->load([
            'department.faculty',
            'instructors',
            'media',
            'modules.lessons.media',
        ]);

        return view('courses.builder', [
            'course' => $course,
            'levels' => CourseLevel::cases(),
            'departments' => Department::query()->with('faculty')->orderBy('name')->get(),
            'publishBlockers' => app(CoursePublishingService::class)->publishBlockers($course),
            'canManage' => request()->user()->can('update', $course),
            'canReview' => request()->user()->can('review', $course),
        ]);
    }

    public function update(UpdateCourseSettingsRequest $request, Course $course, MediaUploadService $media, EnrollmentService $enrollments): RedirectResponse
    {
        $data = $request->validated();

        // Seats freed by raising (or lifting) the cap should auto-promote the waitlist.
        $previousCapacity = $course->capacity;

        $course->update([
            'title' => $data['title'],
            'code' => $data['code'],
            'department_id' => $data['department_id'],
            'level' => $data['level'],
            'visibility' => $data['visibility'],
            'enrollment_mode' => $data['enrollment_mode'],
            'capacity' => $data['capacity'] ?? null,
            'enrollment_opens_at' => $data['enrollment_opens_at'] ?? null,
            'enrollment_closes_at' => $data['enrollment_closes_at'] ?? null,
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'learning_objectives' => $request->objectives(),
        ]);

        // A raised or removed cap may open seats for waitlisted students.
        $newCapacity = $course->capacity;
        if ($newCapacity === null || ($previousCapacity !== null && $newCapacity > $previousCapacity)) {
            $enrollments->capacityChanged($course);
        }

        // Replace the cover image (keep exactly one), if a new file was supplied.
        if ($request->hasFile('cover')) {
            $existing = $course->cover();
            $media->upload($request->file('cover'), MediaPurpose::CourseCovers, $course);
            if ($existing) {
                $media->destroy($existing);
            }
        }

        return redirect()
            ->route('courses.edit', $course)
            ->with('status', 'Course settings saved.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        $title = $course->title;
        $course->delete();

        return redirect()
            ->route('courses.index')
            ->with('status', "“{$title}” was deleted.");
    }
}
