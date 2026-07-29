{{--
    Student dashboard (Section 10): continue-learning, progress overview, upcoming due
    dates, recent badged grades and certificates — all their own live data.
--}}

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-ui.stat label="Courses in progress" :value="$stats['inProgress']" icon="book" tone="crimson" />
    <x-ui.stat label="Average progress" :value="$stats['averageProgress'].'%'" icon="chart" tone="crimson">
        <x-slot name="caption">Across your active courses</x-slot>
    </x-ui.stat>
    <x-ui.stat label="Completed" :value="$stats['completed']" icon="check" tone="success" />
    <x-ui.stat label="Awaiting approval" :value="$stats['awaiting']" icon="clock" tone="gold" />
</div>

<div class="grid gap-6 lg:grid-cols-3">
    {{-- Continue learning --}}
    <x-ui.card class="lg:col-span-2" :padding="false">
        <x-slot name="header">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-display text-lg font-semibold text-ink">Continue learning</h3>
                <x-ui.button size="sm" variant="ghost" :href="route('learning.index')">My Learning</x-ui.button>
            </div>
        </x-slot>

        <div class="p-5">
            @if ($continueLearning->isEmpty())
                <x-ui.empty-state icon="graduation" title="Your learning journey starts here"
                    description="Browse the catalogue and enrol in a course to see your progress and next lessons here.">
                    <x-slot name="action">
                        <x-ui.button :href="route('catalogue.index')">Browse the catalogue</x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($continueLearning as $enrollment)
                        @php $course = $enrollment->course; $percent = (int) $enrollment->progress_percent; @endphp
                        <li class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                            <span class="relative flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-crimson to-crimson-dark">
                                @if ($course->coverUrl())
                                    <img src="{{ $course->coverUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <span class="font-display text-xs font-bold text-white/90">{{ $course->code }}</span>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink">{{ $course->title }}</p>
                                <p class="truncate text-xs text-ink/60">{{ $course->code }} · {{ $course->department?->name ?? 'No department' }}</p>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <div class="h-1 w-full max-w-[12rem] overflow-hidden rounded-full bg-ink/5">
                                        <div class="h-full rounded-full bg-crimson" style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-medium text-ink/50">{{ $percent }}%</span>
                                </div>
                            </div>
                            <x-ui.button size="sm" variant="secondary" :href="route('learn.resume', $course)">Continue</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-ui.card>

    {{-- Upcoming due dates --}}
    <x-ui.card :padding="false">
        <x-slot name="header">
            <h3 class="font-display text-lg font-semibold text-ink">Upcoming deadlines</h3>
        </x-slot>
        <div class="p-5">
            @forelse ($upcoming as $assignment)
                @php $due = $assignment->due_at; $soon = $due->diffInDays(now()) <= 3; @endphp
                <div class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                    <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $soon ? 'bg-crimson/10 text-crimson' : 'bg-ink/5 text-ink/60' }}">
                        <x-ui.icon name="clock" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $assignment->title }}</p>
                        <p class="truncate text-xs text-ink/60">{{ $assignment->course?->title }}</p>
                        <p class="mt-0.5 text-xs {{ $soon ? 'font-medium text-crimson' : 'text-ink/55' }}">
                            Due {{ $due->diffForHumans() }} · {{ $due->format('j M') }}
                        </p>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="check" title="All caught up" description="No upcoming assignment deadlines." />
            @endforelse
        </div>
    </x-ui.card>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Recent grades --}}
    <x-ui.card :padding="false">
        <x-slot name="header">
            <h3 class="font-display text-lg font-semibold text-ink">Recent grades</h3>
        </x-slot>
        <div class="p-5">
            @forelse ($recentGrades as $grade)
                <div class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $grade['title'] }}</p>
                        <p class="truncate text-xs text-ink/60">{{ $grade['course'] }}</p>
                    </div>
                    <span class="shrink-0 text-sm font-semibold text-ink/80">{{ $grade['percent'] }}%</span>
                    @if ($grade['label'])
                        <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold"
                            style="background-color: {{ $grade['color'] ?? '#E7E5E4' }}22; color: {{ $grade['color'] ?? '#1C1917' }}">
                            {{ $grade['label'] }}
                        </span>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="clipboard-check" title="No grades yet"
                    description="Your graded quizzes and assignments will appear here." />
            @endforelse
        </div>
    </x-ui.card>

    {{-- Certificates --}}
    <x-ui.card :padding="false">
        <x-slot name="header">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-display text-lg font-semibold text-ink">Your certificates</h3>
                <x-ui.button size="sm" variant="ghost" :href="route('certificates.mine')">View all</x-ui.button>
            </div>
        </x-slot>
        <div class="p-5">
            @forelse ($certificates as $certificate)
                <div class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0 {{ ! $loop->last ? 'border-b border-line' : '' }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gold/15 text-gold-ink">
                        <x-ui.icon name="certificate" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $certificate->course?->title }}</p>
                        <p class="truncate text-xs text-ink/60">Issued {{ $certificate->issued_at?->format('j M Y') }}</p>
                    </div>
                    <x-ui.button size="sm" variant="ghost" :href="route('certificates.download', $certificate)">View</x-ui.button>
                </div>
            @empty
                <x-ui.empty-state icon="certificate" title="No certificates yet"
                    description="Complete a course to earn your first certificate." />
            @endforelse
        </div>
    </x-ui.card>
</div>
