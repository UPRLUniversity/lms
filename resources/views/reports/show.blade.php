<x-app-layout :title="$report->label()">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('reports.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line text-ink/65 hover:bg-surface" aria-label="Back to report centre">
                    <x-ui.icon name="chevron-left" class="h-5 w-5" />
                </a>
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $report->label() }}</h2>
                    <p class="text-sm text-ink/65">{{ $report->description() }}</p>
                </div>
            </div>
        </div>

        {{-- Filter form (GET → re-renders this page with the preview) --}}
        <x-ui.card>
            <form method="GET" action="{{ route('reports.show', $report->key()) }}" class="space-y-4">
                @include('reports.filters.'.$report->key(), ['options' => $options, 'filters' => $filters])

                <div class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                    <x-ui.button type="submit" name="apply" value="1">
                        <x-ui.icon name="search" class="h-4 w-4" /> Run report
                    </x-ui.button>
                    @if ($applied)
                        <a href="{{ route('reports.show', $report->key()) }}" class="text-sm text-ink/65 hover:text-crimson">Reset</a>
                    @endif
                </div>
            </form>
        </x-ui.card>

        {{-- Results --}}
        @if ($applied)
            {{-- Headline metrics (compliance %s, certification counts) --}}
            @if (! empty($summary))
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    @foreach ($summary as $metric)
                        <x-ui.stat :label="$metric['label']" :value="$metric['value']" :tone="$metric['tone'] ?? 'crimson'" />
                    @endforeach
                </div>
            @endif

            <x-ui.card :padding="false">
                <x-slot name="header">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-ink">Preview</h3>
                            <p class="text-sm text-ink/65">{{ number_format($results->total()) }} {{ \Illuminate\Support\Str::plural('row', $results->total()) }} matched</p>
                        </div>

                        {{-- Exports: one POST carrying the current filters; the clicked button picks the format --}}
                        <form method="POST" action="{{ route('reports.export', $report->key()) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            @include('reports.partials.hidden-filters', ['filters' => $filters])
                            <span class="text-sm text-ink/65">Export:</span>
                            <x-ui.button type="submit" name="format" value="xlsx" size="sm" variant="secondary"><x-ui.icon name="download" class="h-4 w-4" /> Excel</x-ui.button>
                            <x-ui.button type="submit" name="format" value="csv" size="sm" variant="secondary"><x-ui.icon name="download" class="h-4 w-4" /> CSV</x-ui.button>
                            <x-ui.button type="submit" name="format" value="pdf" size="sm" variant="secondary"><x-ui.icon name="download" class="h-4 w-4" /> PDF</x-ui.button>
                        </form>
                    </div>
                </x-slot>

                @if ($results->total() === 0)
                    <div class="p-5">
                        <x-ui.empty-state icon="search" title="No rows matched"
                            description="Try widening the filters above, then run the report again." />
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-surface/60 text-left">
                                    @foreach ($report->headings() as $heading)
                                        <th class="whitespace-nowrap px-4 py-3 font-semibold text-ink/70">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($results as $row)
                                    <tr class="hover:bg-surface/40">
                                        @foreach ($row as $cell)
                                            <td class="whitespace-nowrap px-4 py-2.5 text-ink/80">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($results->hasPages())
                        <div class="border-t border-line px-4 py-3">
                            {{ $results->links() }}
                        </div>
                    @endif
                @endif
            </x-ui.card>
        @else
            <x-ui.card>
                <x-ui.empty-state icon="chart" title="Set your filters, then run the report"
                    description="Choose the filters above and select “Run report” to preview the data. You can export the full result set to Excel, CSV or PDF." />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
