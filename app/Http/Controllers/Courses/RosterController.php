<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Exports\RosterExport;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The per-course roster for instructors and admins: tabbed by status, searchable, with
 * a live capacity meter, a withdraw action, the approval queue (pending tab) and a CSV
 * export. AJAX requests get just the table partial (no full reload), like the admin
 * data tables.
 */
class RosterController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function index(Request $request, Course $course): ViewContract
    {
        $this->authorize('viewRoster', $course);

        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $enrollments = $course->enrollments()
            ->with(['user.media', 'approver'])
            ->when(in_array($status, EnrollmentStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('user', function ($u) use ($search) {
                $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('enrolled_at')
            ->paginate(20)
            ->withQueryString();

        // One grouped query powers both the tab counts and the capacity meter.
        $counts = $course->enrollments()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $seatsTaken = (int) ($counts[EnrollmentStatus::Active->value] ?? 0)
            + (int) ($counts[EnrollmentStatus::Pending->value] ?? 0);

        $data = [
            'course' => $course,
            'enrollments' => $enrollments,
            'counts' => $counts,
            'statuses' => EnrollmentStatus::cases(),
            'activeStatus' => $status,
            'search' => $search,
            'seatsTaken' => $seatsTaken,
            'waitlistCount' => (int) ($counts[EnrollmentStatus::Waitlisted->value] ?? 0),
            'canManage' => $request->user()->can('manageRoster', $course),
            'canApprove' => $request->user()->can('approveEnrollments', $course),
            'canEnroll' => $request->user()->can('enrollOthers', $course),
        ];

        if ($request->ajax() || $request->wantsJson()) {
            return view('courses.roster._table', $data);
        }

        // The student picker for the "add student" panel — students not already
        // holding a live place. Loaded only on the full page.
        if ($data['canEnroll']) {
            $data['enrollableStudents'] = $this->enrollableStudents($course);
        }

        return view('courses.roster.index', $data);
    }

    /**
     * Withdraw a student from the roster (staff action). Frees the seat and triggers
     * auto-promotion from the waitlist.
     */
    public function destroy(Course $course, Enrollment $enrollment): RedirectResponse|JsonResponse
    {
        abort_unless($enrollment->course_id === $course->id, 404);
        $this->authorize('withdraw', $enrollment);

        $name = $enrollment->user->name;
        $this->enrollments->withdraw($enrollment);

        $message = "{$name} was withdrawn.";

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    /**
     * Download the roster as a CSV (maatwebsite/excel).
     */
    public function export(Course $course): BinaryFileResponse
    {
        $this->authorize('viewRoster', $course);

        $filename = 'roster-'.str($course->code)->lower()->slug().'-'.now()->format('Ymd').'.csv';

        return Excel::download(new RosterExport($course), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Students who don't currently hold a live enrolment in this course — the only
     * sensible targets for a direct staff enrolment.
     *
     * @return Collection<int, User>
     */
    private function enrollableStudents(Course $course): Collection
    {
        $taken = $course->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
            ])
            ->pluck('user_id');

        return User::query()
            ->role(Role::Student->value)
            ->whereNotIn('id', $taken)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'email']);
    }
}
