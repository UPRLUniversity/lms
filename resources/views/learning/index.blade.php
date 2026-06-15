@php
    use App\Enums\EnrollmentStatus;
    use Illuminate\Support\Str;
@endphp

<x-app-layout title="My Learning">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">My Learning</h2>
                <p class="mt-1 text-ink/70">
                    @if ($enrollments->isEmpty())
                        Your enrolled courses will appear here.
                    @else
                        {{ $activeCount }} active {{ Str::plural('course', $activeCount) }} · {{ $enrollments->count() }} total
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="ghost" :href="route('learning.history')">
                    <x-ui.icon name="clock" class="h-5 w-5" /> History
                </x-ui.button>
                <x-ui.button variant="secondary" :href="route('catalogue.index')">
                    <x-ui.icon name="search" class="h-5 w-5" /> Browse the catalogue
                </x-ui.button>
            </div>
        </div>

        @if ($enrollments->isEmpty())
            <x-ui.empty-state
                icon="graduation"
                title="You haven't enrolled yet"
                description="Find a course in the catalogue and enrol to start learning.">
                <x-slot name="action">
                    <x-ui.button :href="route('catalogue.index')">Explore courses</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($enrollments as $enrollment)
                    @php
                        $course = $enrollment->course;
                        $cover = $course->coverUrl();
                        $status = $enrollment->status;
                    @endphp

                    <div class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition hover:shadow-md">
                        <div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="" class="h-full w-full object-cover">
                            @else
                                <x-brand.sunburst class="pointer-events-none absolute -right-6 -top-6 h-40 w-40 text-white/10" />
                                <span class="absolute bottom-3 left-4 font-display text-xl font-bold text-white/90">{{ $course->code }}</span>
                            @endif
                            <span class="absolute left-3 top-3">
                                <x-ui.badge :variant="$status->badge()" solid>
                                    @if ($status === EnrollmentStatus::Waitlisted)
                                        Waitlisted #{{ $enrollment->waitlistPosition() }}
                                    @else
                                        {{ $status->label() }}
                                    @endif
                                </x-ui.badge>
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-xs font-medium uppercase tracking-wide text-crimson">{{ $course->code }}</p>
                            <h3 class="mt-1 font-display text-lg font-semibold leading-snug text-ink line-clamp-2">{{ $course->title }}</h3>
                            <p class="mt-1 text-sm text-ink/50">{{ $course->department?->name ?? 'No department' }}</p>

                            {{-- Progress bar for courses being learnt --}}
                            @if (in_array($status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true))
                                @php $percent = (int) $enrollment->progress_percent; @endphp
                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-xs font-medium text-ink/60">
                                        <span>{{ $percent }}% complete</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-ink/5">
                                        <div class="h-full rounded-full {{ $percent >= 100 ? 'bg-success' : 'bg-crimson' }}" style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Status-aware action --}}
                            <div class="mt-auto pt-4">
                                @switch($status)
                                    @case(EnrollmentStatus::Active)
                                        <x-ui.button size="sm" class="w-full" :href="route('learn.resume', $course)">
                                            Continue learning
                                        </x-ui.button>
                                        @break
                                    @case(EnrollmentStatus::Completed)
                                        <x-ui.button size="sm" variant="secondary" class="w-full" :href="route('learn.resume', $course)">
                                            <x-ui.icon name="check" class="h-4 w-4" stroke-width="2.5" /> Completed — revisit
                                        </x-ui.button>
                                        @break
                                    @case(EnrollmentStatus::Pending)
                                        <div class="flex items-center justify-center gap-2 rounded-xl bg-gold/15 px-4 py-2 text-sm font-medium text-gold">
                                            <x-ui.icon name="clock" class="h-4 w-4" /> Pending approval
                                        </div>
                                        @break
                                    @case(EnrollmentStatus::Waitlisted)
                                        <div class="flex items-center justify-center gap-2 rounded-xl bg-ink/5 px-4 py-2 text-sm font-medium text-ink">
                                            <x-ui.icon name="users" class="h-4 w-4" /> #{{ $enrollment->waitlistPosition() }} on the waitlist
                                        </div>
                                        @break
                                @endswitch

                                @if (in_array($status, [EnrollmentStatus::Active, EnrollmentStatus::Pending, EnrollmentStatus::Waitlisted], true))
                                    <form method="POST" action="{{ route('enrollment.withdraw', $enrollment) }}"
                                          x-data
                                          @submit.prevent="if (await window.uprlConfirm({
                                              title: 'Withdraw from this course?',
                                              text: @js('You can re-enrol later if a place is available.'),
                                              confirmText: 'Yes, withdraw',
                                          })) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="mt-2 w-full rounded-lg py-1.5 text-center text-xs font-medium text-ink/50 hover:text-crimson focus-ring">
                                            {{ $status === EnrollmentStatus::Waitlisted ? 'Leave the waitlist' : 'Withdraw' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
