@php
    $user = auth()->user();
    $primaryRole = $user->roles->first()?->name;
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ') ?: $user->name;
@endphp

<x-app-layout title="Dashboard">
    <div class="mx-auto max-w-7xl space-y-8">
        {{-- Greeting + role --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">Welcome back, {{ $firstName }}</h2>
                    @if ($primaryRole)
                        <x-ui.role-badge :role="$primaryRole" />
                    @endif
                </div>
                <p class="mt-1 text-ink/70">
                    @if ($isAdmin)
                        Here’s what’s happening across {{ config('brand.short') }}.
                    @elseif ($isStaff)
                        Here’s an overview of your teaching. Let’s keep building.
                    @elseif ($isAuditor)
                        A read-only overview of {{ config('brand.short') }}.
                    @else
                        Here’s an overview of your learning. Let’s keep the momentum going.
                    @endif
                </p>
            </div>
        </div>

        {{-- ADMIN / AUDITOR: platform overview --}}
        @if ($isAdmin || $isAuditor)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat label="Courses" :value="$stats['courses']" icon="book" tone="crimson" />
                <x-ui.stat label="Awaiting review" :value="$stats['inReview']" icon="clock" tone="gold" />
                <x-ui.stat label="Published" :value="$stats['published']" icon="check" tone="success" />
                <x-ui.stat label="People" :value="$stats['people']" icon="users" tone="crimson" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @if ($stats['inReview'] > 0)
                    <x-ui.card>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-display text-lg font-semibold text-ink">Courses need a decision</h3>
                                <p class="mt-1 text-sm text-ink/70">{{ $stats['inReview'] }} {{ \Illuminate\Support\Str::plural('course', $stats['inReview']) }} submitted for review.</p>
                            </div>
                            <x-ui.icon name="clock" class="h-6 w-6 text-gold" />
                        </div>
                        @unless ($isAuditor)
                            <div class="mt-4">
                                <x-ui.button size="sm" :href="route('courses.index', ['status' => 'review'])">Review now</x-ui.button>
                            </div>
                        @endunless
                    </x-ui.card>
                @endif

                <x-ui.card>
                    <h3 class="font-display text-lg font-semibold text-ink">Quick links</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="secondary" :href="route('courses.index')"><x-ui.icon name="book" class="h-4 w-4" /> Courses</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.faculties.index')"><x-ui.icon name="graduation" class="h-4 w-4" /> Structure</x-ui.button>
                        @unless ($isAuditor)
                            <x-ui.button size="sm" variant="secondary" :href="route('admin.users.index')"><x-ui.icon name="users" class="h-4 w-4" /> People</x-ui.button>
                        @endunless
                        <x-ui.button size="sm" variant="ghost" :href="route('catalogue.index')">View catalogue</x-ui.button>
                    </div>
                </x-ui.card>
            </div>

        {{-- INSTRUCTOR: their courses --}}
        @elseif ($isStaff)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat label="Your courses" :value="$stats['courses']" icon="book" tone="crimson" />
                <x-ui.stat label="Drafts" :value="$stats['drafts']" icon="pencil" tone="crimson" />
                <x-ui.stat label="In review" :value="$stats['inReview']" icon="clock" tone="gold" />
                <x-ui.stat label="Published" :value="$stats['published']" icon="check" tone="success" />
            </div>

            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-lg font-semibold text-ink">Keep building</h3>
                        <p class="mt-1 text-sm text-ink/70">Author a new course or pick up where you left off.</p>
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button variant="secondary" :href="route('courses.index')">Your courses</x-ui.button>
                        <x-ui.button :href="route('courses.create')"><x-ui.icon name="plus" class="h-5 w-5" /> New course</x-ui.button>
                    </div>
                </div>
            </x-ui.card>

        {{-- STUDENT: learning --}}
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.stat label="Courses in progress" :value="$stats['inProgress']" icon="book" tone="crimson" />
                <x-ui.stat label="Completed" :value="$stats['completed']" icon="check" tone="success" />
                <x-ui.stat label="Awaiting approval" :value="$stats['awaiting']" icon="clock" tone="gold" />
            </div>

            <x-ui.card :padding="false">
                <x-slot name="header">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-display text-lg font-semibold text-ink">Continue learning</h3>
                        <x-ui.button size="sm" variant="ghost" :href="route('learning.index')">My Learning</x-ui.button>
                    </div>
                </x-slot>

                <div class="p-5">
                    @if ($continueLearning->isEmpty())
                        <x-ui.empty-state
                            icon="graduation"
                            title="Your learning journey starts here"
                            description="Browse the catalogue and enrol in a course to see your progress and next lessons here.">
                            <x-slot name="action">
                                <x-ui.button :href="route('catalogue.index')">Browse the catalogue</x-ui.button>
                            </x-slot>
                        </x-ui.empty-state>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($continueLearning as $enrollment)
                                @php
                                    $course = $enrollment->course;
                                    $isPublic = $course->status->isPublished() && $course->visibility->isPublic();
                                @endphp
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
                                    </div>
                                    <x-ui.button size="sm" variant="secondary" :href="$isPublic ? route('catalogue.show', $course) : route('learning.index')">
                                        Continue
                                    </x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
