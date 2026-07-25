<x-app-layout :title="$course->title.' · Analytics'">
    <div class="mx-auto max-w-6xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ url()->previous() }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line text-ink/60 hover:bg-surface" aria-label="Back">
                    <x-ui.icon name="chevron-left" class="h-5 w-5" />
                </a>
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $course->title }}</h2>
                    <p class="text-sm text-ink/65">{{ $course->code }} · Course analytics</p>
                </div>
            </div>
            <x-ui.button size="sm" variant="secondary" :href="route('courses.gradebook', $course)">
                <x-ui.icon name="list" class="h-4 w-4" /> Gradebook
            </x-ui.button>
        </div>

        {{-- Knowledge gain --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.stat label="Pre-module average" :value="$knowledgeGain['pre'] === null ? '—' : $knowledgeGain['pre'].'%'" icon="clipboard" tone="neutral" />
            <x-ui.stat label="Post-module average" :value="$knowledgeGain['post'] === null ? '—' : $knowledgeGain['post'].'%'" icon="clipboard-check" tone="crimson" />
            <x-ui.stat label="Knowledge gain"
                :value="$knowledgeGain['gain'] === null ? '—' : ($knowledgeGain['gain'] > 0 ? '+' : '').$knowledgeGain['gain'].'%'"
                icon="sparkles" tone="success">
                <x-slot name="caption">Post-module minus pre-module</x-slot>
            </x-ui.stat>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Progress distribution --}}
            <x-ui.card :padding="false">
                <x-slot name="header">
                    <h3 class="font-display text-lg font-semibold text-ink">Progress distribution</h3>
                    <p class="text-sm text-ink/60">{{ $progress['total'] }} {{ \Illuminate\Support\Str::plural('student', $progress['total']) }} enrolled</p>
                </x-slot>
                <div class="p-5">
                    @if ($progress['total'] === 0)
                        <x-ui.empty-state icon="users" title="No students yet" description="Enrol students to see their progress spread." />
                    @else
                        <x-ui.chart type="bar" :height="240"
                            label="Number of students in each progress band"
                            :data="[
                                'labels' => $progress['labels'],
                                'datasets' => [['label' => 'Students', 'data' => $progress['values'], 'backgroundColor' => 'crimson', 'borderRadius' => 6]],
                            ]"
                            :options="['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]"
                        />
                    @endif
                </div>
            </x-ui.card>

            {{-- Grade distribution (Section 6.5 bands) --}}
            <x-ui.card :padding="false">
                <x-slot name="header">
                    <h3 class="font-display text-lg font-semibold text-ink">Grade distribution</h3>
                    <p class="text-sm text-ink/60">
                        @if ($distribution['scale'])
                            {{ $distribution['scale']->name }} · {{ $distribution['total'] - $distribution['ungraded'] }} graded, {{ $distribution['ungraded'] }} pending
                        @else
                            No grade scale configured
                        @endif
                    </p>
                </x-slot>
                <div class="p-5">
                    @if ($distribution['bands']->isEmpty() || $distribution['bands']->sum('count') === 0)
                        <x-ui.empty-state icon="chart" title="No final grades yet" description="Grades appear here once students complete gradable items." />
                    @else
                        <x-ui.chart type="bar" :height="240"
                            label="Number of students in each grade band"
                            :data="[
                                'labels' => $distribution['bands']->map(fn ($b) => $b['band']->label)->all(),
                                'datasets' => [[
                                    'label' => 'Students',
                                    'data' => $distribution['bands']->map(fn ($b) => $b['count'])->all(),
                                    'backgroundColor' => $distribution['bands']->map(fn ($b) => $b['band']->color ?? '#C8102E')->all(),
                                    'borderRadius' => 6,
                                ]],
                            ]"
                            :options="['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]"
                        />
                    @endif
                </div>
            </x-ui.card>
        </div>

        {{-- Assessment performance --}}
        <x-ui.card :padding="false">
            <x-slot name="header">
                <h3 class="font-display text-lg font-semibold text-ink">Assessment performance</h3>
            </x-slot>
            <div class="overflow-x-auto">
                @if ($assessmentStats->isEmpty())
                    <div class="p-5"><x-ui.empty-state icon="clipboard-check" title="No published assessments" description="Publish an assessment to see its performance here." /></div>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-surface/60 text-left">
                                <th class="px-4 py-3 font-semibold text-ink/70">Assessment</th>
                                <th class="px-4 py-3 font-semibold text-ink/70">Placement</th>
                                <th class="px-4 py-3 font-semibold text-ink/70">Attempts</th>
                                <th class="px-4 py-3 font-semibold text-ink/70">Average</th>
                                <th class="px-4 py-3 font-semibold text-ink/70">Pass rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($assessmentStats as $stat)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-ink">{{ $stat['assessment']->title }}</td>
                                    <td class="px-4 py-2.5 text-ink/70">{{ $stat['placement']->label() }}</td>
                                    <td class="px-4 py-2.5 text-ink/80">{{ $stat['attempts'] }}</td>
                                    <td class="px-4 py-2.5 text-ink/80">{{ $stat['average'] === null ? '—' : $stat['average'].'%' }}</td>
                                    <td class="px-4 py-2.5 text-ink/80">{{ $stat['passRate'] === null ? '—' : $stat['passRate'].'%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </x-ui.card>

        {{-- Hardest questions --}}
        <x-ui.card :padding="false">
            <x-slot name="header">
                <h3 class="font-display text-lg font-semibold text-ink">Hardest questions</h3>
                <p class="text-sm text-ink/60">Ranked by share of answers marked wrong</p>
            </x-slot>
            <div class="p-5">
                @forelse ($hardest as $item)
                    <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-ink">{{ \Illuminate\Support\Str::limit(strip_tags($item['question']->prompt), 90) }}</p>
                            <p class="text-xs text-ink/55">{{ $item['responses'] }} {{ \Illuminate\Support\Str::plural('response', $item['responses']) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-ink/5">
                                <div class="h-full rounded-full bg-crimson" style="width: {{ $item['wrongRate'] }}%"></div>
                            </div>
                            <span class="w-12 text-right text-sm font-semibold text-crimson">{{ $item['wrongRate'] }}%</span>
                        </div>
                    </div>
                @empty
                    <x-ui.empty-state icon="check-circle" title="Nothing stands out"
                        description="Once students answer auto-graded questions, the trickiest ones surface here." />
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
