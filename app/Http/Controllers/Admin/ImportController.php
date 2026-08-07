<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkImport;
use App\Models\Assignment;
use App\Models\Course;
use App\Support\Import\ImportDefinition;
use App\Support\Import\ImportFormatException;
use App\Support\Import\ImportRegistry;
use App\Support\Import\ImportRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ONE controller for every bulk import. The flow — template → upload → preview →
 * confirm — is identical whatever is being imported, so it lives here once and the
 * ImportDefinition supplies the domain (see App\Support\Import).
 *
 * The scope (a Course for questions, an Assignment for marks) arrives as an optional
 * route parameter and is resolved by the definition's own key, so adding an importer
 * never touches this file.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportRunner $runner,
    ) {}

    /** The upload screen: what the file needs, the template, the file input. */
    public function create(Request $request, string $import, ?string $scopeId = null): View
    {
        [$definition, $scope] = $this->resolve($request, $import, $scopeId);

        return view('admin.imports.create', [
            'definition' => $definition,
            'scope' => $scope,
            'scopeId' => $scopeId,
        ]);
    }

    /** The CSV template, header plus worked examples. */
    public function template(Request $request, string $import, ?string $scopeId = null): StreamedResponse
    {
        [$definition] = $this->resolve($request, $import, $scopeId);

        $body = $this->runner->template($definition);

        return response()->streamDownload(
            fn () => print $body,
            $definition->key().'-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Parse and judge the upload, then show every row and its verdict. Writes nothing —
     * the admin can look and walk away.
     */
    public function preview(Request $request, string $import, ?string $scopeId = null): View|RedirectResponse
    {
        [$definition, $scope] = $this->resolve($request, $import, $scopeId);

        $request->validate([
            'file' => [
                'required', 'file', 'max:8192',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,'
                    .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream',
            ],
        ], [
            'file.mimetypes' => 'Please upload a .csv or .xlsx spreadsheet.',
            'file.max' => 'The file may not be larger than 8 MB.',
        ]);

        $upload = $request->file('file');
        $extension = Str::lower($upload->getClientOriginalExtension() ?: 'csv');
        $extension = in_array($extension, ['csv', 'xlsx', 'xls'], true) ? $extension : 'csv';

        $token = $this->runner->stage($definition, $upload->get(), $extension);

        try {
            $report = $this->runner->analyse($definition, $this->runner->staged($definition, $token), $scope, $request->user());
        } catch (ImportFormatException $e) {
            // Nothing to preview — send them back to the upload screen with the reason.
            $this->runner->discard($definition, $token);

            return $this->backToUpload($definition, $scopeId)->with('error', $e->getMessage());
        }

        return view('admin.imports.preview', [
            'definition' => $definition,
            'scope' => $scope,
            'scopeId' => $scopeId,
            'runner' => $this->runner,
            'report' => $report,
            'token' => $token,
            'queues' => $report['counts']['valid'] > ImportRunner::QUEUE_THRESHOLD,
        ]);
    }

    /**
     * Confirm. Re-reads and RE-JUDGES the staged file rather than trusting the preview:
     * minutes may have passed, and someone else may have created the very account this
     * file is about to create.
     */
    public function store(Request $request, string $import, ?string $scopeId = null): RedirectResponse
    {
        [$definition, $scope] = $this->resolve($request, $import, $scopeId);

        $request->validate(['token' => ['required', 'string']]);

        $token = (string) $request->input('token');
        $path = $this->runner->staged($definition, $token);

        if (! $path) {
            return $this->backToUpload($definition, $scopeId)
                ->with('error', 'That upload has expired. Please upload the file again.');
        }

        try {
            $report = $this->runner->analyse($definition, $path, $scope, $request->user());
        } catch (ImportFormatException $e) {
            $this->runner->discard($definition, $token);

            return $this->backToUpload($definition, $scopeId)->with('error', $e->getMessage());
        }

        // Large imports run off-request so the browser isn't left hanging; the staged
        // file is the job's input and the job deletes it when done.
        if ($report['counts']['valid'] > ImportRunner::QUEUE_THRESHOLD) {
            ProcessBulkImport::dispatch(
                $definition->key(),
                $this->runner->relativePath($definition, $token),
                $scope ? $scope::class : null,
                $scope?->getKey(),
                $request->user()->id,
            );

            return $this->backToScreen($definition, $scope)->with(
                'status',
                "Your import of {$report['counts']['valid']} rows is running in the background — we'll let you know when it's done.",
            );
        }

        $result = $this->runner->import($definition, $path, $scope, $request->user());
        $this->runner->discard($definition, $token);

        return $this->backToScreen($definition, $scope)->with('status', $this->summary($definition, $result));
    }

    /*
    |--------------------------------------------------------------------------
    | Plumbing
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{0: ImportDefinition, 1: Model|null}
     */
    private function resolve(Request $request, string $import, ?string $scopeId): array
    {
        $definition = $this->registry->get($import);
        $scope = $this->scopeFor($definition, $scopeId);

        abort_unless($definition->authorize($request->user(), $scope), 403);

        return [$definition, $scope];
    }

    /**
     * Which model an import is scoped to. Kept as an explicit map rather than inferred,
     * because a route parameter deciding which Eloquent model to load is exactly the
     * kind of inference that becomes an IDOR the day someone adds an importer.
     */
    private function scopeFor(ImportDefinition $definition, ?string $scopeId): ?Model
    {
        if ($scopeId === null) {
            return null;
        }

        return match ($definition->key()) {
            'questions' => Course::findOrFail($scopeId),
            'grades' => Assignment::findOrFail($scopeId),
            default => null,
        };
    }

    private function summary(ImportDefinition $definition, array $result): string
    {
        $noun = Str::plural($definition->noun(), $result['imported']);
        $message = "Imported {$result['imported']} {$noun}.";

        return $result['skipped'] > 0
            ? $message." {$result['skipped']} ".Str::plural('row', $result['skipped']).' skipped.'
            : $message;
    }

    private function backToUpload(ImportDefinition $definition, ?string $scopeId): RedirectResponse
    {
        return redirect()->route('admin.imports.create', array_filter([
            'import' => $definition->key(),
            'scopeId' => $scopeId,
        ]));
    }

    private function backToScreen(ImportDefinition $definition, ?Model $scope): RedirectResponse
    {
        [$route, $parameters] = $definition->returnRoute($scope);

        return redirect()->route($route, $parameters);
    }
}
