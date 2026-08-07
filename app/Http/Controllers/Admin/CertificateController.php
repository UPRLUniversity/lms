<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Certificates\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The certificate registry: every issued certificate, searchable, plus the completed
 * enrolments still missing one (manual "Issue"). Admins mutate; the read-only auditor
 * browses (certificates.view, granted automatically). Mutations (issue/reissue/revoke/
 * restore) additionally require certificates.manage via CertificatePolicy::manage.
 */
class CertificateController extends Controller
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Certificate::class);

        $search = trim((string) $request->query('search', ''));

        $certificates = Certificate::query()
            ->with(['user:id,name,email', 'course:id,title,slug', 'template:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('serial', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('course', fn ($c) => $c->where('title', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('issued_at')
            ->paginate(20)
            ->withQueryString();

        // Completed enrolments with no certificate yet — the manual "Issue" queue.
        $missing = Enrollment::query()
            ->where('status', EnrollmentStatus::Completed->value)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('certificates')
                    ->whereColumn('certificates.user_id', 'enrollments.user_id')
                    ->whereColumn('certificates.course_id', 'enrollments.course_id');
            })
            ->with(['user:id,name,email', 'course:id,title,slug'])
            ->latest('completed_at')
            ->limit(20)
            ->get();

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'missing' => $missing,
            'outcomes' => $this->outcomesFor($missing),
            'search' => $search,
        ]);
    }

    /**
     * The recorded pass/fail verdict for each row of the "completed, not yet issued"
     * queue, keyed "userId:courseId". One query for the whole list.
     *
     * Shown, not enforced: a completed course still earns a certificate whatever the final
     * band says, exactly as before. But an admin about to issue one by hand should be able
     * to see that the student's recorded grade is a fail before they click, rather than
     * discovering it from the certificate.
     *
     * @param  Collection<int, Enrollment>  $missing
     * @return Collection<string, bool>
     */
    private function outcomesFor(Collection $missing): Collection
    {
        if ($missing->isEmpty()) {
            return collect();
        }

        return CourseGradeRecord::query()
            ->whereIn('user_id', $missing->pluck('user_id')->unique())
            ->whereIn('course_id', $missing->pluck('course_id')->unique())
            ->current()
            ->get()
            ->mapWithKeys(fn (CourseGradeRecord $record) => [
                $record->user_id.':'.$record->course_id => $record->isPass(),
            ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $this->authorize('create', Certificate::class);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $course = Course::findOrFail($data['course_id']);

        $enrollment = $course->enrollmentFor($user);
        if ($enrollment === null || $enrollment->status !== EnrollmentStatus::Completed) {
            return back()->with('error', "{$user->name} hasn't completed {$course->title} yet.");
        }

        $certificate = $this->certificates->issueManually($user, $course);

        return back()->with('status', "Certificate {$certificate->serial} issued for {$user->name}.");
    }

    public function reissue(Certificate $certificate): RedirectResponse
    {
        $this->authorize('manage', $certificate);

        $this->certificates->reissue($certificate);

        return back()->with('status', "Certificate {$certificate->serial} is being re-issued — the PDF will refresh shortly.");
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorize('manage', $certificate);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->certificates->revoke($certificate, $data['reason']);

        return back()->with('status', "Certificate {$certificate->serial} was revoked.");
    }

    public function restore(Certificate $certificate): RedirectResponse
    {
        $this->authorize('manage', $certificate);

        $this->certificates->restore($certificate);

        return back()->with('status', "Certificate {$certificate->serial} is valid again.");
    }
}
