<?php

namespace App\Http\Controllers\Courses;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\BulkEnrollmentImportRequest;
use App\Http\Requests\Courses\BulkEnrollmentPreviewRequest;
use App\Jobs\ProcessEnrollmentImport;
use App\Services\Courses\BulkEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk enrolment from a CSV (email,course_code): upload → preview (per-row problems) →
 * confirm. Valid rows only are imported; imports over 100 rows are queued. The staged
 * file lives on the private 'local' disk under a UUID and is deleted after import.
 */
class BulkEnrollmentController extends Controller
{
    /** Above this many rows the import runs on the queue, not in the request. */
    private const QUEUE_THRESHOLD = 100;

    private const STAGING_DIR = 'enrollment-imports';

    public function __construct(private readonly BulkEnrollmentService $service) {}

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('courses.bulk.create');
    }

    /**
     * Parse the upload and show the preview table. Stages the file so the confirm step
     * can re-read it without a second upload.
     */
    public function preview(BulkEnrollmentPreviewRequest $request): View
    {
        $content = $request->file('file')->get();
        $report = $this->service->analyze($content);

        $token = (string) Str::uuid();
        Storage::disk('local')->put($this->pathFor($token), $content);

        return view('courses.bulk.preview', [
            'report' => $report,
            'token' => $token,
            'queues' => $report['counts']['total'] > self::QUEUE_THRESHOLD,
        ]);
    }

    /**
     * Confirm the import. Re-reads + re-validates the staged file; queues if large,
     * else imports inline and reports the precise outcome.
     */
    public function store(BulkEnrollmentImportRequest $request): RedirectResponse
    {
        $path = $this->pathFor((string) $request->input('token'));

        if (! Storage::disk('local')->exists($path)) {
            return redirect()
                ->route('enrollments.bulk.create')
                ->with('error', 'That import has expired. Please upload the file again.');
        }

        $content = Storage::disk('local')->get($path);
        $report = $this->service->analyze($content);

        // Large imports run off-request; the staged file is the job's input.
        if ($report['counts']['total'] > self::QUEUE_THRESHOLD) {
            ProcessEnrollmentImport::dispatch($path, $request->user()->id);

            return redirect()
                ->route('enrollments.bulk.create')
                ->with('status', "Your import of {$report['counts']['total']} rows has been queued — we'll process it shortly.");
        }

        $result = $this->service->import($content, $request->user());
        Storage::disk('local')->delete($path);

        return redirect()
            ->route('enrollments.bulk.create')
            ->with('status', "Imported {$result['imported']} of {$result['total']} rows. Skipped {$result['skipped']}.");
    }

    /**
     * Download the CSV template (header + sample rows).
     */
    public function template(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $body = $this->service->template();

        return response()->streamDownload(
            fn () => print $body,
            'enrolment-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    private function pathFor(string $token): string
    {
        return self::STAGING_DIR.'/'.$token.'.csv';
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]),
            403,
        );
    }
}
