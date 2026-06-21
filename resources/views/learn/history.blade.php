@php
    use App\Enums\EnrollmentStatus;
    use Illuminate\Support\Str;

    /** @var \Illuminate\Support\Collection $enrollments */
    /** @var \Illuminate\Support\Collection $secondsByCourse */

    $fmtTime = function (int $seconds): string {
        if ($seconds <= 0) return '—';
        if ($seconds < 60) return $seconds.'s';
        $m = intdiv($seconds, 60);
        $h = intdiv($m, 60);
        $m %= 60;
        return $h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m';
    };
@endphp

<x-app-layout title="Learning history">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Learning history</h2>
                <p class="mt-1 text-ink/70">
                    @if ($enrollments->isEmpty())
                        Your course activity will appear here.
                    @else
                        {{ $completedCount }} completed · {{ $enrollments->count() }} {{ Str::plural('course', $enrollments->count()) }} in your history
                    @endif
                </p>
            </div>
            <x-ui.button variant="secondary" :href="route('learning.index')">
                <x-ui.icon name="graduation" class="h-5 w-5" /> My Learning
            </x-ui.button>
        </div>

        @if ($enrollments->isEmpty())
            <x-ui.empty-state
                icon="book"
                title="No learning history yet"
                description="Once you start a course, your progress, time spent and completions show up here.">
                <x-slot name="action">
                    <x-ui.button :href="route('catalogue.index')">Explore courses</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                                <th scope="col" class="px-5 py-3.5">Course</th>
                                <th scope="col" class="px-5 py-3.5">Enrolled</th>
                                <th scope="col" class="px-5 py-3.5 w-48">Progress</th>
                                <th scope="col" class="px-5 py-3.5">Time spent</th>
                                <th scope="col" class="px-5 py-3.5">Completed</th>
                                <th scope="col" class="px-5 py-3.5">Assessment</th>
                                <th scope="col" class="px-5 py-3.5">Certificate</th>
                                <th scope="col" class="px-5 py-3.5"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($enrollments as $enrollment)
                                @php
                                    $course = $enrollment->course;
                                    $percent = (int) $enrollment->progress_percent;
                                    $seconds = (int) ($secondsByCourse[$course->id] ?? 0);
                                    $withdrawn = $enrollment->status === EnrollmentStatus::Withdrawn;
                                @endphp
                                <tr class="{{ $withdrawn ? 'opacity-60' : '' }}">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="relative flex h-9 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-gradient-to-br from-crimson to-crimson-dark">
                                                @if ($course->coverUrl())
                                                    <img src="{{ $course->coverUrl() }}" alt="" class="h-full w-full object-cover">
                                                @else
                                                    <span class="font-display text-[10px] font-bold text-white/90">{{ Str::limit($course->code, 6, '') }}</span>
                                                @endif
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-ink">{{ $course->title }}</p>
                                                <p class="truncate text-xs text-ink/50">{{ $course->code }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-ink/70">{{ $enrollment->enrolled_at?->isoFormat('D MMM YYYY') ?? '—' }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink/5">
                                                <div class="h-full rounded-full {{ $percent >= 100 ? 'bg-success' : 'bg-crimson' }}" style="width: {{ $percent }}%"></div>
                                            </div>
                                            <span class="w-9 text-right text-xs font-medium text-ink/60">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-ink/70">{{ $fmtTime($seconds) }}</td>
                                    <td class="px-5 py-4">
                                        @if ($enrollment->completed_at)
                                            <span class="inline-flex items-center gap-1 text-success">
                                                <x-ui.icon name="check" class="h-4 w-4" stroke-width="2.5" />
                                                {{ $enrollment->completed_at->isoFormat('D MMM YYYY') }}
                                            </span>
                                        @elseif ($withdrawn)
                                            <x-ui.badge variant="crimson">Withdrawn</x-ui.badge>
                                        @else
                                            <span class="text-ink/40">In progress</span>
                                        @endif
                                    </td>
                                    {{-- Section 5: pre/post knowledge-gain per module. --}}
                                    <td class="px-5 py-4">
                                        @php $gains = $gainsByCourse[$course->id] ?? collect(); @endphp
                                        @if ($gains->isEmpty())
                                            <span class="text-ink/30">—</span>
                                        @else
                                            <div class="space-y-1">
                                                @foreach ($gains as $gain)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-green/10 px-2 py-0.5 text-xs text-green"
                                                          title="{{ $gain['module_title'] }}: {{ $gain['pre'] }}% → {{ $gain['post'] }}%">
                                                        <x-ui.icon name="sparkles" class="h-3 w-3" />
                                                        {{ $gain['pre'] }}→{{ $gain['post'] }}% ({{ $gain['gain'] >= 0 ? '+' : '' }}{{ $gain['gain'] }})
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    {{-- Filled in by Section 7 (certificate). --}}
                                    <td class="px-5 py-4 text-ink/30">—</td>
                                    <td class="px-5 py-4 text-right">
                                        @unless ($withdrawn)
                                            <x-ui.button size="sm" variant="ghost" :href="route('learn.resume', $course)">
                                                {{ $enrollment->completed_at ? 'Revisit' : 'Continue' }}
                                            </x-ui.button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
