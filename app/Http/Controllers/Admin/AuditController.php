<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\AuditActivity;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit trail viewer (Section 15).
 *
 * READ-ONLY BY CONSTRUCTION. There is no store, update or destroy action here, and
 * none is reachable elsewhere: AuditActivity refuses both at the model level. The
 * trail is evidence, and evidence you can edit is not evidence.
 *
 * Redaction has already happened at write time (AuditLogger), so nothing this
 * controller can select contains a secret to leak — the viewer renders whatever it
 * is given and does not need to be trusted with the rule.
 */
class AuditController extends Controller
{
    /** Entries per page — enough to scan a session's worth of activity at once. */
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        Gate::authorize('view-audit-log');

        return view('admin.audit.index', [
            'entries' => $this->query($request)->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $this->filters($request),
            'causers' => $this->causerOptions(),
            'categories' => $this->categoryOptions(),
            'events' => $this->eventOptions(),
            'subjectTypes' => $this->subjectTypeOptions(),
            'redactedMarker' => AuditLogger::REDACTED,
        ]);
    }

    /**
     * The same filtered set as the screen, streamed as CSV.
     *
     * Streamed rather than built in memory: an audit table is the one table that only
     * ever grows, and an export that assembles the whole result set first is a memory
     * limit waiting to be hit. Bounded by config('audit.export_limit') regardless.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('view-audit-log');

        $limit = (int) config('audit.export_limit', 5000);
        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request, $limit) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Recorded at', 'Actor', 'Actor email', 'Event', 'Category',
                'Subject type', 'Subject', 'Description', 'Changed fields', 'Before', 'After',
            ]);

            $this->query($request)
                ->with(['causer', 'subject'])
                ->limit($limit)
                ->chunk(200, function ($chunk) use ($handle) {
                    foreach ($chunk as $entry) {
                        fputcsv($handle, $this->csvRow($entry));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function csvRow(AuditActivity $entry): array
    {
        $before = $entry->before();
        $after = $entry->after();
        $fields = array_unique([...array_keys($before), ...array_keys($after)]);

        return [
            $entry->created_at?->toDateTimeString() ?? '',
            $entry->causer?->name ?? 'System',
            $entry->causer?->email ?? '',
            $entry->eventLabel(),
            (string) $entry->log_name,
            $entry->subject_type ? class_basename($entry->subject_type) : '',
            $entry->subjectLabel(),
            (string) $entry->description,
            implode(' · ', $fields),
            $this->flatten($before),
            $this->flatten($after),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function flatten(array $values): string
    {
        $parts = [];

        foreach ($values as $key => $value) {
            $parts[] = $key.'='.(is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value));
        }

        return implode(' · ', $parts);
    }

    /**
     * @return Builder<AuditActivity>
     */
    private function query(Request $request): Builder
    {
        $filters = $this->filters($request);

        return AuditActivity::query()
            // Eager loaded because the list renders both on every row — the N+1 this
            // avoids would be 60 extra queries per page.
            ->with(['causer', 'subject'])
            ->causedByUser($filters['user'] ? (int) $filters['user'] : null)
            ->ofCategory($filters['category'])
            ->ofEvent($filters['event'])
            ->ofSubjectType($filters['subject_type'])
            ->betweenDates($filters['from'], $filters['to'])
            ->when($filters['q'], function (Builder $query, string $term) {
                $query->where(function (Builder $q) use ($term) {
                    $q->where('description', 'like', "%{$term}%")
                        ->orWhere('event', 'like', "%{$term}%");
                });
            })
            ->latest('id');
    }

    /**
     * @return array<string, string|null>
     */
    private function filters(Request $request): array
    {
        return [
            'user' => $request->string('user')->toString() ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'event' => $request->string('event')->toString() ?: null,
            'subject_type' => $request->string('subject_type')->toString() ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'q' => $request->string('q')->toString() ?: null,
        ];
    }

    /**
     * Only people who have actually caused an entry, so the filter is a short list
     * of real actors rather than every account on the system.
     *
     * @return array<int, string>
     */
    private function causerOptions(): array
    {
        $ids = AuditActivity::query()
            ->where('causer_type', User::class)
            ->distinct()
            ->pluck('causer_id')
            ->filter()
            ->all();

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function categoryOptions(): array
    {
        $used = AuditActivity::query()->distinct()->pluck('log_name')->filter()->all();

        $options = [];
        foreach ($used as $category) {
            $options[$category] = ucfirst(str_replace('-', ' ', (string) $category));
        }

        ksort($options);

        return $options;
    }

    /**
     * Every event the log actually contains, labelled. Restricted to what is present
     * so the dropdown never offers a filter that returns nothing.
     *
     * @return array<string, string>
     */
    private function eventOptions(): array
    {
        $used = AuditActivity::query()->distinct()->pluck('event')->filter()->all();

        $options = [];
        foreach ($used as $event) {
            $options[$event] = AuditEvent::tryLabel((string) $event) ?? $event;
        }

        asort($options);

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function subjectTypeOptions(): array
    {
        $used = AuditActivity::query()->distinct()->pluck('subject_type')->filter()->all();

        $options = [];
        foreach ($used as $type) {
            $options[$type] = class_basename((string) $type);
        }

        asort($options);

        return $options;
    }
}
