{{--
    Instructor dashboard (Section 10): headline teaching figures with deep links into
    the queues, plus a per-course list linking to each course's analytics drill-down.
--}}

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-ui.stat label="Students" :value="number_format($stats['totalEnrollments'])" icon="users" tone="crimson">
        <x-slot name="caption">Active &amp; completed across your courses</x-slot>
    </x-ui.stat>
    <x-ui.stat label="Average progress"
        :value="$stats['averageProgress'] === null ? '—' : $stats['averageProgress'].'%'" icon="chart" tone="crimson">
        <x-slot name="caption">Mean completion across the roster</x-slot>
    </x-ui.stat>
    <x-ui.stat label="Average score"
        :value="$stats['averageScore'] === null ? '—' : $stats['averageScore'].'%'" icon="clipboard-check" tone="success">
        <x-slot name="caption">Mean graded assessment result</x-slot>
    </x-ui.stat>
    <x-ui.card class="flex flex-col justify-between">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-ink/70">Awaiting grading</p>
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg {{ $stats['ungraded'] > 0 ? 'bg-gold/10 text-gold-ink' : 'bg-ink/5 text-ink/65' }}">
                <x-ui.icon name="inbox" class="h-5 w-5" />
            </span>
        </div>
        <div class="mt-2 flex items-end justify-between gap-2">
            <p class="font-display text-3xl font-semibold text-ink">{{ number_format($stats['ungraded']) }}</p>
            @if ($stats['ungraded'] > 0)
                <x-ui.button size="sm" variant="ghost" :href="route('grading.index')">Grade now <x-ui.icon name="arrow-right" class="h-4 w-4" /></x-ui.button>
            @endif
        </div>
    </x-ui.card>
</div>

<x-ui.card :padding="false">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-display text-lg font-semibold text-ink">Your courses</h3>
            <x-ui.button size="sm" :href="route('courses.create')"><x-ui.icon name="plus" class="h-4 w-4" /> New course</x-ui.button>
        </div>
    </x-slot>

    <div class="p-5">
        @forelse ($courses as $course)
            <div class="flex flex-wrap items-center gap-4 py-3 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-medium text-ink">{{ $course->title }}</p>
                        <x-ui.badge :variant="$course->status->badge()">{{ $course->status->label() }}</x-ui.badge>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-ink/65">
                        {{ $course->code }} ·
                        {{ (int) $course->active_count }} active ·
                        {{ (int) $course->completed_count }} completed
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <x-ui.button size="sm" variant="secondary" :href="route('courses.analytics', $course)">
                        <x-ui.icon name="chart" class="h-4 w-4" /> Analytics
                    </x-ui.button>
                    <x-ui.button size="sm" variant="ghost" :href="route('courses.gradebook', $course)">Gradebook</x-ui.button>
                </div>
            </div>
        @empty
            <x-ui.empty-state icon="book" title="No courses yet"
                description="Author your first course to see teaching analytics here.">
                <x-slot name="action">
                    <x-ui.button :href="route('courses.create')">Create a course</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @endforelse
    </div>
</x-ui.card>
