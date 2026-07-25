<x-app-layout title="Report centre">
    <div class="mx-auto max-w-5xl space-y-8">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Report centre</h2>
            <p class="mt-1 text-ink/70">Filterable reports across learners, teaching, compliance and certification — exportable to Excel, CSV and PDF.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($reports as $report)
                <a href="{{ route('reports.show', $report->key()) }}"
                   class="group rounded-xl border border-line bg-card p-5 shadow-sm transition hover:border-crimson/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-crimson/10 text-crimson">
                            <x-ui.icon :name="$report->icon()" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-display text-lg font-semibold text-ink group-hover:text-crimson">{{ $report->label() }}</h3>
                            <p class="mt-1 text-sm text-ink/65">{{ $report->description() }}</p>
                        </div>
                        <x-ui.icon name="arrow-right" class="ml-auto h-5 w-5 shrink-0 text-ink/30 transition group-hover:translate-x-0.5 group-hover:text-crimson" />
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-app-layout>
