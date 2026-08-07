@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Course $course */
    /** @var ?\App\Models\GradeScale $scale */
    /** @var \Illuminate\Support\Collection $rows */
    /** @var \Illuminate\Support\Collection $distribution */

    $columns = $rows->first()['items'] ?? collect();
    $maxDistribution = max(1, $distribution->max('count') ?? 1);
@endphp

<x-app-layout :title="'Gradebook · '.$course->title">
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/50 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
            </a>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">Gradebook</h2>
                    <p class="mt-1 text-ink/70">
                        {{ $course->title }} · {{ $course->code }} · {{ $scale?->name ?? 'No grade scale' }}@if ($scale?->passMark() !== null) · pass mark {{ $scale->passMark() }}%@endif
                    </p>
                </div>
                <x-ui.button variant="secondary" :href="route('courses.gradebook.export', $course)">
                    <x-ui.icon name="download" class="h-5 w-5" /> Export CSV
                </x-ui.button>
            </div>
        </div>

        @if (! $scale || $columns->isEmpty())
            <x-ui.empty-state
                icon="clipboard-check"
                title="No gradable coursework yet"
                description="Publish at least one required assessment or assignment for this course to see a gradebook." />
        @else
            {{-- Class distribution --}}
            @if ($distribution->isNotEmpty())
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <h3 class="font-display text-sm font-semibold text-ink">Grade distribution</h3>
                    <div class="mt-3 flex items-end gap-3">
                        @foreach ($distribution as $d)
                            <div class="flex flex-1 flex-col items-center gap-1">
                                <div class="flex h-16 w-full items-end">
                                    <div @class(['w-full rounded-t-md', 'bg-success' => $d['band']->color === 'success', 'bg-gold' => $d['band']->color === 'gold', 'bg-crimson' => $d['band']->color === 'crimson', 'bg-ink' => $d['band']->color === 'ink', 'bg-ink/30' => $d['band']->color === 'neutral'])
                                         style="height: {{ $d['count'] > 0 ? max(8, ($d['count'] / $maxDistribution) * 100) : 2 }}%"
                                         title="{{ $d['band']->label }}: {{ $d['count'] }}"></div>
                                </div>
                                <span class="text-[11px] font-medium text-ink/60">{{ $d['band']->label }}</span>
                                <span class="text-[11px] text-ink/40">{{ $d['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Search + sort --}}
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search students…"
                       class="block w-full max-w-xs rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <x-ui.button type="submit" size="sm" variant="secondary">Search</x-ui.button>
                <a href="{{ route('courses.gradebook', array_filter(['course' => $course, 'search' => $search, 'sort' => $sort === 'grade' ? 'name' : 'grade'])) }}"
                   class="ml-auto inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-crimson focus-ring rounded">
                    <x-ui.icon name="arrows-up-down" class="h-4 w-4" /> Sort by {{ $sort === 'grade' ? 'name' : 'grade' }}
                </a>
            </form>

            @if ($rows->isEmpty())
                <x-ui.empty-state icon="users" title="No matching students" description="Try a different search." />
            @else
                <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                                    <th scope="col" class="sticky left-0 z-10 bg-card px-5 py-3.5">Student</th>
                                    @foreach ($columns as $col)
                                        <th scope="col" class="px-4 py-3.5 whitespace-nowrap" title="{{ $col->title() }}">
                                            {{ Str::limit($col->title(), 16) }}
                                        </th>
                                    @endforeach
                                    <th scope="col" class="px-5 py-3.5 whitespace-nowrap">Course grade</th>
                                    @if ($canRecompute)
                                        <th scope="col" class="px-5 py-3.5"><span class="sr-only">Actions</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($rows as $row)
                                    @php $summary = $row['summary']; @endphp
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-card px-5 py-4">
                                            <div class="flex items-center gap-3">
                                                <x-ui.avatar :user="$row['user']" size="sm" />
                                                <div class="min-w-0">
                                                    <p class="truncate font-medium text-ink">{{ $row['user']->name }}</p>
                                                    <p class="truncate text-xs text-ink/50">{{ $row['user']->email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        @foreach ($row['items'] as $item)
                                            @php $band = ! $item->pending && $item->percent !== null ? $scale->bandFor($item->percent) : null; @endphp
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                @if ($item->pending)
                                                    @php
                                                        $deepLink = $item->isAssignment()
                                                            ? route('grading.assignments.index', ['course' => $course->slug, 'assignment' => $item->model->id])
                                                            : route('grading.index');
                                                    @endphp
                                                    <a href="{{ $deepLink }}" class="inline-flex items-center gap-1 text-xs text-gold-ink hover:underline focus-ring rounded">
                                                        <x-ui.icon name="clock" class="h-3.5 w-3.5" /> Pending
                                                    </a>
                                                @elseif ($band)
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <x-ui.badge :variant="$band->color">{{ $band->label }}</x-ui.badge>
                                                        <span class="text-xs text-ink/40">{{ round($item->percent) }}%</span>
                                                    </span>
                                                @else
                                                    <span class="text-ink/30">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            @php
                                                // Recorded students are judged by the scale they completed under;
                                                // everyone else by where the live scale puts them right now.
                                                $isPass = $row['record'] ? $row['record']->isPass() : $summary?->isPass();
                                            @endphp
                                            @if ($row['record'])
                                                <span class="inline-flex items-center gap-1.5 font-medium text-ink">{{ $row['record']->formatResult() }}</span>
                                            @elseif ($summary?->percent !== null)
                                                <span class="inline-flex items-center gap-1.5">
                                                    {{ $scale->formatResult($summary->percent, $summary->band) }}
                                                    @if ($summary->provisional)
                                                        <x-ui.badge variant="gold">Provisional</x-ui.badge>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-ink/30">—</span>
                                            @endif
                                            @if ($isPass !== null)
                                                <x-ui.badge :variant="$isPass ? 'success' : 'crimson'" class="ml-1.5">{{ $isPass ? 'Pass' : 'Fail' }}</x-ui.badge>
                                            @endif
                                        </td>
                                        @if ($canRecompute)
                                            <td class="px-5 py-4 text-right">
                                                @if ($row['record'])
                                                    <form method="POST" action="{{ route('courses.gradebook.recompute', [$course, $row['user']]) }}"
                                                          onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Recompute this grade?', text: 'Supersedes the recorded grade with a fresh one against the current scale. The old value stays in history.', confirmText: 'Recompute' }).then(ok => ok && this.submit());">
                                                        @csrf
                                                        <x-ui.button size="sm" variant="ghost" type="submit">Recompute</x-ui.button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
