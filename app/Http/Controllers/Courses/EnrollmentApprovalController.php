<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The approval queue shared by admins and lead instructors: pending requests across
 * every course the viewer may approve, with approve / reject (+ optional note) and a
 * bulk-approve action.
 */
class EnrollmentApprovalController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can(Permission::EnrollmentsApprove->value), 403);

        $pending = Enrollment::query()
            ->with(['user.media', 'course.department'])
            ->where('status', EnrollmentStatus::Pending->value)
            ->whereHas('course', fn (Builder $q) => $this->constrainToApprovable($q, $user))
            ->orderBy('enrolled_at')
            ->paginate(20);

        return view('courses.approvals.index', [
            'pending' => $pending,
        ]);
    }

    public function approve(Enrollment $enrollment, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $enrollment);

        $this->enrollments->approve($enrollment, $request->user());

        return $this->respond($request, "{$enrollment->user->name} was approved.");
    }

    public function reject(Enrollment $enrollment, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('reject', $enrollment);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->enrollments->reject($enrollment, $request->user(), $validated['note'] ?? null);

        return $this->respond($request, "{$enrollment->user->name}'s request was declined.");
    }

    /**
     * Approve several pending requests at once. Each is authorized individually, so a
     * lead instructor can only ever approve requests on courses they lead.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $approved = 0;

        Enrollment::query()
            ->whereIn('id', $validated['ids'])
            ->where('status', EnrollmentStatus::Pending->value)
            ->with('course')
            ->each(function (Enrollment $enrollment) use ($request, &$approved) {
                if ($request->user()->can('approve', $enrollment)) {
                    $this->enrollments->approve($enrollment, $request->user());
                    $approved++;
                }
            });

        return back()->with('status', $approved === 1
            ? '1 request approved.'
            : "{$approved} requests approved.");
    }

    /**
     * Answer JSON for an AJAX caller (the roster's in-place actions), or a redirect
     * back with a flash for a normal form post (the approvals page).
     */
    private function respond(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }

    /**
     * Limit the queue to courses the viewer may decide on: admins see every course;
     * instructors see only the ones they lead.
     *
     * @param  Builder<Course>  $query
     */
    private function constrainToApprovable(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Admin->value) || $user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        // Courses this user leads, read straight off the pivot (robust inside the
        // nested whereHas that wraps this constraint).
        $query->whereIn('courses.id', function ($sub) use ($user) {
            $sub->select('course_id')
                ->from('course_instructor')
                ->where('user_id', $user->id)
                ->where('is_lead', true);
        });
    }
}
