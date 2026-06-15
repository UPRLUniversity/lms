@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Database\Eloquent\Collection $lessons */
    /** @var \Illuminate\Support\Collection $rows */

    $avg = $rows->isNotEmpty() ? (int) round($rows->avg('percent')) : 0;
    $completed = $rows->where('percent', 100)->count();
@endphp

<x-app-layout :title="'Progress · '.$course->title">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div>
            <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/50 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
            </a>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">Learner progress</h2>
                    <p class="mt-1 text-ink/70">{{ $course->title }} · {{ $course->code }}</p>
                </div>
                <div class="flex gap-2">
                    <x-ui.button variant="secondary" :href="route('courses.roster', $course)">
                        <x-ui.icon name="users" class="h-5 w-5" /> Roster
                    </x-ui.button>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.stat label="Enrolled learners" :value="$rows->count()" icon="users" tone="crimson" />
            <x-ui.stat label="Average progress" :value="$avg.'%'" icon="chart" tone="gold" />
            <x-ui.stat label="Completed" :value="$completed" icon="check" tone="success" />
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty-state
                icon="users"
                title="No active learners yet"
                description="Once students enrol and start learning, their progress and per-lesson completion show up here." />
        @else
            <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                                <th scope="col" class="sticky left-0 z-10 bg-card px-5 py-3.5">Student</th>
                                <th scope="col" class="px-5 py-3.5 w-40">Progress</th>
                                <th scope="col" class="px-5 py-3.5 whitespace-nowrap">Last activity</th>
                                <th scope="col" class="px-5 py-3.5">
                                    Lessons
                                    <span class="ml-1 font-normal normal-case text-ink/40">({{ $lessons->count() }})</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($rows as $row)
                                @php
                                    $percent = $row['percent'];
                                    $completedIds = $row['completedLessonIds'];
                                @endphp
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
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink/5">
                                                <div class="h-full rounded-full {{ $percent >= 100 ? 'bg-success' : 'bg-crimson' }}" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <span class="w-9 text-right text-xs font-medium text-ink/60">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-ink/60">
                                        {{ $row['lastActivity']?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        {{-- Per-lesson completion heat-strip --}}
                                        <div class="flex flex-wrap gap-1" aria-hidden="true">
                                            @foreach ($lessons as $l)
                                                @php $isDone = $completedIds->has($l->id); @endphp
                                                <span @class([
                                                          'h-4 w-4 rounded-sm',
                                                          'bg-success' => $isDone,
                                                          'bg-ink/10' => ! $isDone,
                                                      ])
                                                      title="{{ $l->title }} — {{ $isDone ? 'completed' : 'not done' }}"></span>
                                            @endforeach
                                        </div>
                                        <span class="sr-only">{{ $completedIds->count() }} of {{ $lessons->count() }} lessons completed</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs text-ink/50">
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-success"></span> Completed</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-ink/10"></span> Not done</span>
            </div>
        @endif
    </div>
</x-app-layout>
