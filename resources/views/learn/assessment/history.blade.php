@php use App\Enums\AttemptStatus; @endphp

<x-learn-layout :title="'Attempts — '.$assessment->title">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <a href="{{ route('assessments.start', [$course, $assessment]) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-crimson focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> {{ $assessment->title }}
        </a>
        <h1 class="mt-3 font-display text-2xl font-semibold text-ink">Your attempts</h1>

        @if ($attempts->isEmpty())
            <div class="mt-6">
                <x-ui.empty-state icon="clipboard" title="No attempts yet" description="Start the assessment to record your first attempt." />
            </div>
        @else
            <ul class="mt-5 divide-y divide-line overflow-hidden rounded-xl border border-line bg-card">
                @foreach ($attempts as $past)
                    <li class="flex items-center justify-between gap-3 px-4 py-3.5">
                        <div>
                            <p class="text-sm font-medium text-ink">Attempt {{ $past->attempt_number }}</p>
                            <p class="text-xs text-ink/50">{{ $past->submitted_at?->format('M j, Y g:i A') ?? 'In progress' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.badge :variant="$past->status->badge()">{{ $past->status->label() }}</x-ui.badge>
                            @if ($past->status === AttemptStatus::Graded)
                                <span class="font-display text-lg font-semibold {{ $past->passed ? 'text-green' : 'text-crimson' }}">{{ $past->percentage }}%</span>
                            @endif
                            @if ($past->status === AttemptStatus::InProgress)
                                <a href="{{ route('attempts.show', $past) }}" class="text-sm font-medium text-crimson focus-ring rounded">Resume</a>
                            @else
                                <a href="{{ route('attempts.result', $past) }}" class="text-sm font-medium text-crimson focus-ring rounded">View</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-learn-layout>
