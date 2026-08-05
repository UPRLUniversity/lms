@php
    use App\Enums\AuditEvent;
    $hasFilters = collect($filters)->filter()->isNotEmpty();
@endphp

<x-app-layout title="Audit trail">
    <x-slot name="breadcrumbs">
        <x-ui.breadcrumbs :items="[
            ['label' => 'Administration'],
            ['label' => 'Audit trail'],
        ]" />
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Audit trail</h2>
                <p class="mt-1 max-w-2xl text-ink/70">
                    Every consequential change, who made it and what moved. Append-only — entries are
                    never edited or removed. Credentials and passwords are recorded as
                    <em>changed</em>, never by value.
                </p>
            </div>

            <x-ui.button variant="secondary"
                         :href="route('admin.audit.export', request()->query())">
                <x-ui.icon name="download" class="h-5 w-5" /> Export CSV
            </x-ui.button>
        </div>

        {{-- Filters. A GET form, so a filtered view is a shareable URL. --}}
        <x-ui.card>
            <form method="GET" action="{{ route('admin.audit.index') }}" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field name="q" label="Search" hint="Description or event name">
                        <x-ui.input id="q" name="q" :value="$filters['q']" placeholder="e.g. reorder" />
                    </x-ui.field>

                    <x-ui.field name="user" label="Actor">
                        <select id="user" name="user"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option value="">Anyone</option>
                            @foreach ($causers as $id => $name)
                                <option value="{{ $id }}" @selected((string) $id === (string) $filters['user'])>{{ $name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="category" label="Area">
                        <select id="category" name="category"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option value="">All areas</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected($value === $filters['category'])>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="event" label="Event">
                        <select id="event" name="event"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option value="">All events</option>
                            @foreach ($events as $value => $label)
                                <option value="{{ $value }}" @selected($value === $filters['event'])>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="subject_type" label="Record type">
                        <select id="subject_type" name="subject_type"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option value="">All records</option>
                            @foreach ($subjectTypes as $value => $label)
                                <option value="{{ $value }}" @selected($value === $filters['subject_type'])>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="from" label="From" type="date">
                        <x-ui.input id="from" name="from" type="date" :value="$filters['from']" />
                    </x-ui.field>

                    <x-ui.field name="to" label="To" type="date">
                        <x-ui.input id="to" name="to" type="date" :value="$filters['to']" />
                    </x-ui.field>

                    <div class="flex items-end gap-2">
                        <x-ui.button type="submit">
                            <x-ui.icon name="search" class="h-5 w-5" /> Filter
                        </x-ui.button>
                        @if ($hasFilters)
                            <x-ui.button variant="ghost" :href="route('admin.audit.index')">Clear</x-ui.button>
                        @endif
                    </div>
                </div>
            </form>
        </x-ui.card>

        @if ($entries->isEmpty())
            <x-ui.empty-state
                icon="clipboard"
                title="{{ $hasFilters ? 'Nothing matches those filters' : 'No activity recorded yet' }}"
                description="{{ $hasFilters
                    ? 'Try widening the date range or clearing a filter.'
                    : 'The trail fills as people sign in and change things.' }}">
                @if ($hasFilters)
                    <x-slot name="action">
                        <x-ui.button variant="secondary" :href="route('admin.audit.index')">Clear filters</x-ui.button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        @else
            <x-ui.card :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($entries as $entry)
                        @php
                            $event = $entry->auditEvent();
                            $before = $entry->before();
                            $after = $entry->after();
                            $context = $entry->context();
                            $fields = array_values(array_unique([...array_keys($before), ...array_keys($after)]));
                            $expandable = $fields !== [] || $context !== [];
                        @endphp

                        <li>
                            {{-- A <details> rather than an Alpine panel: expandable rows are
                                 exactly what it is for, it is keyboard-operable and
                                 screen-reader-announced with no JS, and it survives print. --}}
                            <details class="group" @if ($expandable) @else open @endif>
                                <summary @class([
                                    'flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3.5 transition-colors hover:bg-surface focus-ring',
                                    'cursor-default' => ! $expandable,
                                ])>
                                    <span @class([
                                        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                        'bg-crimson/10 text-crimson' => $event?->isDestructive(),
                                        'bg-ink/5 text-ink/60' => ! $event?->isDestructive(),
                                    ])>
                                        <x-ui.icon :name="$event?->isDestructive() ? 'flag' : 'check'" class="h-4 w-4" />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-ink">
                                            <span class="font-medium">{{ $entry->causer?->name ?? 'System' }}</span>
                                            <span class="text-ink/70">{{ $entry->description }}</span>
                                        </span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink/50">
                                            <span>{{ $entry->eventLabel() }}</span>
                                            @if ($entry->subject_type)
                                                <span aria-hidden="true">·</span>
                                                <span>{{ class_basename($entry->subject_type) }}: {{ $entry->subjectLabel() }}</span>
                                            @endif
                                            <span aria-hidden="true">·</span>
                                            <time datetime="{{ $entry->created_at?->toIso8601String() }}">
                                                {{ $entry->created_at?->format('d M Y, H:i') }}
                                            </time>
                                        </span>
                                    </span>

                                    @if ($fields !== [])
                                        <span class="hidden shrink-0 sm:inline-flex">
                                            <x-ui.badge>{{ count($fields) }} {{ \Illuminate\Support\Str::plural('field', count($fields)) }}</x-ui.badge>
                                        </span>
                                    @endif

                                    @if ($expandable)
                                        <x-ui.icon name="chevron-right"
                                                   class="h-4 w-4 shrink-0 text-ink/40 transition-transform group-open:rotate-90" />
                                    @endif
                                </summary>

                                @if ($expandable)
                                    <div class="border-t border-line bg-surface/50 px-5 py-4">
                                        @if ($fields !== [])
                                            <div class="overflow-x-auto">
                                                <table class="w-full min-w-[36rem] text-sm">
                                                    <caption class="sr-only">Fields changed by this action</caption>
                                                    <thead>
                                                        <tr class="text-left text-xs uppercase tracking-wide text-ink/50">
                                                            <th scope="col" class="pb-2 pr-4 font-semibold">Field</th>
                                                            <th scope="col" class="pb-2 pr-4 font-semibold">Before</th>
                                                            <th scope="col" class="pb-2 font-semibold">After</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-line/70">
                                                        @foreach ($fields as $fieldName)
                                                            <tr class="align-top">
                                                                <th scope="row" class="py-2 pr-4 text-left font-medium text-ink">
                                                                    {{ \Illuminate\Support\Str::headline($fieldName) }}
                                                                </th>

                                                                @if ($entry->isRedacted($fieldName))
                                                                    {{-- The whole point: the fact of the change, never the value. --}}
                                                                    <td colspan="2" class="py-2">
                                                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-crimson/10 px-2 py-1 text-xs font-medium text-crimson">
                                                                            <x-ui.icon name="lock" class="h-3.5 w-3.5" />
                                                                            Changed — value withheld
                                                                        </span>
                                                                    </td>
                                                                @else
                                                                    <td class="py-2 pr-4 text-ink/60">
                                                                        <x-admin.audit-value :value="$before[$fieldName] ?? null" />
                                                                    </td>
                                                                    <td class="py-2 font-medium text-ink">
                                                                        <x-admin.audit-value :value="$after[$fieldName] ?? null" />
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif

                                        @if ($context !== [])
                                            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-ink/60">
                                                @foreach ($context as $ctxKey => $ctxValue)
                                                    <div class="flex gap-1.5">
                                                        <dt class="font-medium text-ink/70">{{ \Illuminate\Support\Str::headline($ctxKey) }}:</dt>
                                                        <dd><x-admin.audit-value :value="$ctxValue" /></dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif
                                    </div>
                                @endif
                            </details>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <div>{{ $entries->links() }}</div>
        @endif
    </div>
</x-app-layout>
