{{--
    Admin / auditor dashboard (Section 10). Every figure is a real query via
    DashboardService; the auditor sees the same numbers with no mutating links.
--}}

{{-- Headline stat cards --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-ui.stat label="Active users (30d)" :value="number_format($stats['activeUsers30d'])" icon="users" tone="crimson">
        <x-slot name="caption">Signed in within the last 30 days</x-slot>
    </x-ui.stat>
    <x-ui.stat label="Active enrolments" :value="number_format($stats['activeEnrollments'])" icon="graduation" tone="crimson">
        <x-slot name="caption">{{ number_format($stats['totalEnrollments']) }} enrolments all-time</x-slot>
    </x-ui.stat>
    <x-ui.stat label="Completion rate" :value="$stats['completionRate'].'%'" icon="check" tone="success">
        <x-slot name="caption">Of everyone who took a seat</x-slot>
    </x-ui.stat>
    <x-ui.stat label="Certificates issued" :value="number_format($stats['certificatesIssued'])" icon="certificate" tone="gold">
        <x-slot name="caption">Active (non-revoked)</x-slot>
    </x-ui.stat>
</div>

{{-- Needs attention: the three pending queues, deep-linked (read-only for auditor) --}}
<div class="grid gap-4 lg:grid-cols-3">
    @php
        $pending = [
            ['label' => 'Course reviews', 'count' => $stats['pendingReviews'], 'icon' => 'flag', 'route' => route('courses.index', ['status' => 'review'])],
            ['label' => 'Enrolment approvals', 'count' => $stats['pendingApprovals'], 'icon' => 'user-plus', 'route' => $isAuditor ? null : route('enrollments.approvals')],
            ['label' => 'Grading queue', 'count' => $stats['gradingQueue'], 'icon' => 'clipboard-check', 'route' => route('grading.index')],
        ];
    @endphp
    @foreach ($pending as $item)
        <x-ui.card>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg {{ $item['count'] > 0 ? 'bg-gold/15 text-gold-ink' : 'bg-ink/5 text-ink/50' }}">
                        <x-ui.icon :name="$item['icon']" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-2xl font-semibold text-ink">{{ number_format($item['count']) }}</p>
                        <p class="text-sm text-ink/70">{{ $item['label'] }}</p>
                    </div>
                </div>
                @if ($item['route'] && $item['count'] > 0)
                    <x-ui.button size="sm" variant="ghost" :href="$item['route']" aria-label="Open {{ $item['label'] }}">
                        Open <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-3">
    {{-- Enrolment trend (12 months) --}}
    <x-ui.card class="lg:col-span-2" :padding="false">
        <x-slot name="header">
            <h3 class="font-display text-lg font-semibold text-ink">Enrolment trend</h3>
            <p class="text-sm text-ink/60">New enrolments over the last 12 months</p>
        </x-slot>
        <div class="p-5">
            @if (array_sum($trend['values']) === 0)
                <x-ui.empty-state icon="chart" title="No enrolments yet"
                    description="The trend chart will populate as students enrol." />
            @else
                <x-ui.chart
                    type="bar"
                    label="Enrolments per month for the last 12 months"
                    :data="[
                        'labels' => $trend['labels'],
                        'datasets' => [[
                            'label' => 'Enrolments',
                            'data' => $trend['values'],
                            'backgroundColor' => 'crimson',
                            'borderRadius' => 6,
                            'maxBarThickness' => 34,
                        ]],
                    ]"
                    :options="[
                        'plugins' => ['legend' => ['display' => false]],
                        'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
                    ]"
                />
            @endif
        </div>
    </x-ui.card>

    {{-- Top courses by enrolment --}}
    <x-ui.card :padding="false">
        <x-slot name="header">
            <h3 class="font-display text-lg font-semibold text-ink">Top courses</h3>
            <p class="text-sm text-ink/60">By total enrolment</p>
        </x-slot>
        <div class="p-5">
            @forelse ($topCourses as $row)
                <div class="flex items-center gap-3 py-2 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-crimson/10 text-xs font-semibold text-crimson">{{ $loop->iteration }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $row['course']->title }}</p>
                        <p class="truncate text-xs text-ink/55">{{ $row['course']->department?->name ?? 'No department' }}</p>
                    </div>
                    <span class="shrink-0 text-sm font-semibold text-ink/80">{{ number_format($row['enrollments']) }}</span>
                </div>
            @empty
                <x-ui.empty-state icon="book" title="No enrolments yet" description="Courses will rank here once students enrol." />
            @endforelse
        </div>
    </x-ui.card>
</div>

{{-- Recent activity --}}
<x-ui.card :padding="false">
    <x-slot name="header">
        <h3 class="font-display text-lg font-semibold text-ink">Recent activity</h3>
    </x-slot>
    <div class="p-5">
        @forelse ($activity as $event)
            <div class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ \App\Enums\NotificationType::toneClasses($event['tone']) }}">
                    <x-ui.icon :name="$event['icon']" class="h-4 w-4" />
                </span>
                <p class="min-w-0 flex-1 truncate text-sm text-ink/80">{{ $event['text'] }}</p>
                <time class="shrink-0 text-xs text-ink/50" datetime="{{ $event['when']?->toIso8601String() }}">{{ $event['when']?->diffForHumans() }}</time>
            </div>
        @empty
            <x-ui.empty-state icon="clock" title="Nothing yet" description="Enrolments and completions will appear here." />
        @endforelse
    </div>
</x-ui.card>
