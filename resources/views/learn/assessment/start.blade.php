@php use App\Enums\AttemptStatus; @endphp

<x-learn-layout :title="$assessment->title">
    <div class="mx-auto min-h-screen max-w-2xl px-4 py-10 sm:py-16">
        <a href="{{ route('learn.resume', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-crimson focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
        </a>

        <div class="mt-6 rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                    <x-ui.icon name="clipboard" class="h-6 w-6" />
                </span>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-ink/50">{{ $assessment->placement->label() }} assessment</p>
                    <h1 class="font-display text-2xl font-semibold text-ink">{{ $assessment->title }}</h1>
                </div>
            </div>

            @if ($assessment->instructions)
                <x-ui.prose class="mt-5 text-sm">{!! $assessment->instructions !!}</x-ui.prose>
            @endif

            {{-- Meta grid --}}
            <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-surface p-3">
                    <dt class="text-xs text-ink/50">Questions</dt>
                    <dd class="mt-0.5 font-display text-lg font-semibold text-ink">{{ $assessment->questionCount() }}</dd>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <dt class="text-xs text-ink/50">Time limit</dt>
                    <dd class="mt-0.5 font-display text-lg font-semibold text-ink">{{ $assessment->isTimed() ? $assessment->time_limit_minutes.' min' : 'None' }}</dd>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <dt class="text-xs text-ink/50">Pass mark</dt>
                    <dd class="mt-0.5 font-display text-lg font-semibold text-ink">{{ $assessment->passing_score }}%</dd>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <dt class="text-xs text-ink/50">Attempts</dt>
                    <dd class="mt-0.5 font-display text-lg font-semibold text-ink">
                        {{ $attemptsLeft === null ? 'Unlimited' : $attemptsLeft.' left' }}
                    </dd>
                </div>
            </dl>

            {{-- Window state --}}
            @if ($assessment->opensInFuture())
                <p class="mt-5 rounded-xl border border-gold/40 bg-gold/10 p-3 text-sm text-ink/80">
                    Opens {{ $assessment->available_from->format('M j, Y g:i A') }}.
                </p>
            @elseif ($assessment->hasClosed())
                <p class="mt-5 rounded-xl border border-crimson/30 bg-crimson/5 p-3 text-sm text-ink/80">
                    This assessment closed on {{ $assessment->available_until->format('M j, Y g:i A') }}.
                </p>
            @endif

            {{-- Action --}}
            <div class="mt-6">
                @if ($inProgress)
                    <form method="POST" action="{{ route('attempts.store', [$course, $assessment]) }}">
                        @csrf
                        <x-ui.button type="submit" class="w-full justify-center sm:w-auto">
                            <x-ui.icon name="play" class="h-4 w-4" /> Resume attempt
                        </x-ui.button>
                    </form>
                @elseif ($canStart)
                    <form method="POST" action="{{ route('attempts.store', [$course, $assessment]) }}">
                        @csrf
                        <x-ui.button type="submit" class="w-full justify-center sm:w-auto">
                            <x-ui.icon name="play" class="h-4 w-4" /> Start attempt
                        </x-ui.button>
                    </form>
                    @if ($assessment->isTimed())
                        <p class="mt-2 text-xs text-ink/50">The timer starts as soon as you begin and keeps running if you leave.</p>
                    @endif
                @else
                    <p class="rounded-xl bg-surface p-3 text-sm text-ink/60">
                        @if ($attemptsLeft !== null && $attemptsLeft <= 0)
                            You've used all your attempts.
                        @else
                            This assessment isn't available right now.
                        @endif
                    </p>
                @endif
            </div>
        </div>

        {{-- Prior attempts --}}
        @if ($history->isNotEmpty())
            <div class="mt-6">
                <h2 class="font-display text-sm font-semibold text-ink/70">Your attempts</h2>
                <ul class="mt-2 divide-y divide-line overflow-hidden rounded-xl border border-line bg-card">
                    @foreach ($history as $past)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-ink">Attempt {{ $past->attempt_number }}</span>
                                <x-ui.badge :variant="$past->status->badge()">{{ $past->status->label() }}</x-ui.badge>
                            </div>
                            <div class="flex items-center gap-3">
                                @if ($past->status === AttemptStatus::Graded)
                                    <span class="text-sm font-semibold {{ $past->passed ? 'text-green' : 'text-crimson' }}">{{ $past->percentage }}%</span>
                                @endif
                                <a href="{{ route('attempts.result', $past) }}" class="text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">View</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-learn-layout>
