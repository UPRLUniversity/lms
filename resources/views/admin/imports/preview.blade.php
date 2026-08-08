@php
    /** @var \App\Support\Import\ImportDefinition $definition */
    /** @var \App\Support\Import\ImportRunner $runner */
    use App\Support\Import\ImportRow;

    $counts = $report['counts'];
    $columns = $definition->columns();
    $params = array_filter(['import' => $definition->key(), 'scopeId' => $scopeId]);

    // Show the columns that carry meaning at a glance. Every cell is still available
    // on the row — but a 12-column question sheet rendered in full is unreadable, and
    // the point of this screen is the VERDICT, not re-reading the spreadsheet.
    $shown = array_slice($columns, 0, 3);
@endphp

<x-app-layout title="Check import">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Check before importing</h2>
                <p class="mt-1 text-ink/70">
                    Nothing has been saved yet. Only the rows marked <span class="font-medium text-success">ready</span> will be imported.
                </p>
            </div>
            <x-ui.button variant="ghost" :href="route('admin.imports.create', $params)">
                <x-ui.icon name="arrow-left" class="h-5 w-5" /> Upload a different file
            </x-ui.button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <x-ui.stat label="Rows found" :value="$counts['total']" icon="document-text" tone="neutral" />
            <x-ui.stat label="Ready to import" :value="$counts['valid']" icon="check" tone="success" />
            <x-ui.stat label="Will be skipped" :value="$counts['invalid']" icon="x" tone="crimson" />
        </div>

        @if ($report['truncated'])
            <div class="rounded-xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-ink/80">
                This file is longer than {{ number_format(\App\Support\Import\SpreadsheetReader::MAX_ROWS) }} rows, so only the first
                {{ number_format(\App\Support\Import\SpreadsheetReader::MAX_ROWS) }} are shown and will be imported. Split the file to import the rest.
            </div>
        @endif

        @if ($queues)
            <div class="rounded-xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-ink/80">
                That's more than {{ \App\Support\Import\ImportRunner::QUEUE_THRESHOLD }} rows, so the import will run in the background once you confirm.
                You'll get a notification when it finishes.
            </div>
        @endif

        <x-ui.card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/65">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Row</th>
                            @foreach ($shown as $column)
                                <th scope="col" class="px-5 py-3 font-medium">{{ $column->label }}</th>
                            @endforeach
                            <th scope="col" class="px-5 py-3 font-medium">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($report['rows'] as $row)
                            <tr class="hover:bg-surface/60 {{ $row->isOk() ? '' : 'bg-crimson/[0.02]' }}">
                                <td class="px-5 py-3 align-top text-ink/65">{{ $row->line }}</td>

                                @foreach ($shown as $column)
                                    <td class="max-w-xs px-5 py-3 align-top">
                                        <span class="block truncate text-ink" title="{{ $row->get($column->key) }}">
                                            {{ \Illuminate\Support\Str::limit($row->get($column->key), 70) ?: '—' }}
                                        </span>
                                    </td>
                                @endforeach

                                <td class="px-5 py-3 align-top">
                                    <x-ui.badge :variant="$row->isOk() ? 'success' : 'crimson'">
                                        {{ $runner->label($definition, $row->problem) }}
                                    </x-ui.badge>
                                    {{-- What the importer worked out for this row: the resolved
                                         programme, the answer key, the student's name. Confirms
                                         the file was read the way the human meant it. --}}
                                    @if ($row->isOk() && $row->resolved !== [])
                                        <span class="mt-1 block text-xs text-ink/65">
                                            {{ collect($row->resolved)->filter()->join(' · ') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink/65">
                {{ $counts['valid'] }} of {{ $counts['total'] }} {{ \Illuminate\Support\Str::plural('row', $counts['total']) }}
                will be imported.
            </p>

            @if ($counts['valid'] === 0)
                <span class="rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink/65">Nothing to import</span>
            @else
                <form method="POST" action="{{ route('admin.imports.store', $params) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-ui.button type="submit">
                        <x-ui.icon name="check" class="h-5 w-5" stroke-width="2.5" />
                        {{ $queues ? 'Queue import' : 'Import '.$counts['valid'].' '.\Illuminate\Support\Str::plural($definition->noun(), $counts['valid']) }}
                    </x-ui.button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
