@php
    use App\Enums\AttemptStatus;

    $graded = $attempt->status === AttemptStatus::Graded;
    $pending = $attempt->status === AttemptStatus::Submitted;
    $pct = (int) $attempt->percentage;
    $ringColor = $attempt->passed ? 'var(--uprl-green, #0F6B3E)' : 'var(--uprl-crimson, #C8102E)';
    $circumference = 2 * pi() * 52;

    // Render a student's answer for an option-type review item as text.
    $answerText = function (array $item) {
        $byId = collect($item['options'] ?? [])->keyBy('id');
        $r = $item['response'];
        if (is_array($r)) {
            return collect($r)->map(fn ($id) => $byId[$id]['text'] ?? $id)->implode(', ');
        }
        return $byId[$r]['text'] ?? $r;
    };
@endphp

<x-learn-layout :title="'Result — '.$assessment->title">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:py-14">
        <a href="{{ route('assessments.start', [$course, $assessment]) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-crimson focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> {{ $assessment->title }}
        </a>

        {{-- Score / status --}}
        <div class="mt-6 rounded-2xl border border-line bg-card p-6 text-center shadow-sm sm:p-8">
            @if ($graded)
                <div class="relative mx-auto h-32 w-32">
                    <svg class="h-32 w-32 -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" class="text-ink/10" stroke-width="10" />
                        <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $ringColor }}" stroke-width="10" stroke-linecap="round"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - $pct / 100) }}" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="font-display text-3xl font-semibold text-ink">{{ $pct }}%</span>
                    </div>
                </div>
                <p class="mt-4 font-display text-xl font-semibold {{ $attempt->passed ? 'text-green' : 'text-crimson' }}">
                    {{ $attempt->passed ? 'Passed' : 'Not passed' }}
                </p>
                <p class="mt-1 text-sm text-ink/60">
                    {{ rtrim(rtrim(number_format((float) $attempt->score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format((float) $attempt->max_score, 2), '0'), '.') }} points · pass mark {{ $assessment->passing_score }}%
                </p>
            @elseif ($pending)
                <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-gold/15 text-gold-ink">
                    <x-ui.icon name="clock" class="h-7 w-7" />
                </span>
                <p class="mt-4 font-display text-xl font-semibold text-ink">Submitted — awaiting grading</p>
                <p class="mt-1 text-sm text-ink/60">An instructor will grade the written answers. Check back for your final score.</p>
            @endif
        </div>

        {{-- Pre/post knowledge-gain card --}}
        @if ($gain)
            <div class="mt-5 overflow-hidden rounded-2xl border border-green/30 bg-green/5 p-5">
                <div class="flex items-center gap-2 text-green">
                    <x-ui.icon name="sparkles" class="h-5 w-5" />
                    <p class="font-display font-semibold">Knowledge gain — {{ $gain['module_title'] }}</p>
                </div>
                <div class="mt-3 flex items-center justify-center gap-4 text-center">
                    <div>
                        <p class="text-xs text-ink/50">Pre-module</p>
                        <p class="font-display text-2xl font-semibold text-ink">{{ $gain['pre'] }}%</p>
                    </div>
                    <x-ui.icon name="arrow-right" class="h-5 w-5 text-ink/30" />
                    <div>
                        <p class="text-xs text-ink/50">Post-module</p>
                        <p class="font-display text-2xl font-semibold text-ink">{{ $gain['post'] }}%</p>
                    </div>
                    <div class="ml-2 rounded-xl bg-green/15 px-3 py-2">
                        <p class="text-xs text-green/80">Gain</p>
                        <p class="font-display text-2xl font-semibold text-green">{{ $gain['gain'] >= 0 ? '+' : '' }}{{ $gain['gain'] }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Review --}}
        @if ($canReview && ! empty($reviewItems))
            <div class="mt-6 space-y-4">
                <h2 class="font-display text-lg font-semibold text-ink">Review</h2>
                @foreach ($reviewItems as $item)
                    @php $correct = $item['is_correct']; @endphp
                    <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide text-ink/45">Question {{ $item['number'] }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs tabular-nums text-ink/50">
                                    {{ rtrim(rtrim(number_format((float) ($item['points_awarded'] ?? 0), 2), '0'), '.') }}/{{ rtrim(rtrim(number_format((float) $item['points_possible'], 2), '0'), '.') }}
                                </span>
                                @if ($correct === true)
                                    <x-ui.badge variant="success">Correct</x-ui.badge>
                                @elseif ($correct === false)
                                    <x-ui.badge variant="crimson">Incorrect</x-ui.badge>
                                @endif
                            </div>
                        </div>

                        <x-ui.prose class="mt-2 text-sm" :html="$item['prompt']" />

                        <div class="mt-3 space-y-2 text-sm">
                            <div>
                                <span class="text-ink/50">Your answer: </span>
                                @switch($item['type'])
                                    @case('mcq_single') @case('mcq_multi') @case('true_false')
                                        <span class="text-ink">{{ $answerText($item) ?: '—' }}</span>
                                        @break
                                    @case('fill_blank')
                                        <span class="text-ink">{{ is_array($item['response']) ? implode(', ', $item['response']) : ($item['response'] ?: '—') }}</span>
                                        @break
                                    @case('essay')
                                        <p class="mt-1 whitespace-pre-line text-ink">{{ is_string($item['response']) ? $item['response'] : '—' }}</p>
                                        @break
                                    @default
                                        <span class="text-ink/60">See breakdown above</span>
                                @endswitch
                            </div>

                            @if (in_array($item['type'], ['mcq_single', 'mcq_multi', 'true_false', 'fill_blank']) && $correct !== true)
                                <div>
                                    <span class="text-ink/50">Correct answer: </span>
                                    <span class="text-green">{{ is_array($item['correct']) ? implode(', ', $item['correct']) : $item['correct'] }}</span>
                                </div>
                            @endif

                            @if ($item['type'] === 'matching')
                                <ul class="mt-1 space-y-1">
                                    @foreach ($item['correct'] as $pair)
                                        <li class="text-ink/70">{{ $pair['left'] }} → <span class="text-green">{{ $pair['right'] }}</span></li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (! empty($item['feedback']))
                                <div class="rounded-lg bg-surface p-3">
                                    <p class="text-xs font-medium text-ink/50">Instructor feedback</p>
                                    <x-ui.prose class="mt-1 text-sm" :html="$item['feedback']" />
                                </div>
                            @endif

                            @if (! empty($item['explanation']))
                                <div class="rounded-lg border-l-2 border-crimson/30 bg-crimson/5 p-3">
                                    <p class="text-xs font-medium text-ink/50">Explanation</p>
                                    <x-ui.prose class="mt-1 text-sm" :html="$item['explanation']" />
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($graded && ! $canReview)
            <p class="mt-6 rounded-xl bg-surface p-4 text-center text-sm text-ink/55">
                @if ($assessment->review_policy === \App\Enums\ReviewPolicy::AfterClose)
                    Detailed review will be available once the assessment closes.
                @else
                    Per-question review isn't available for this assessment.
                @endif
            </p>
        @endif

        <div class="mt-8 flex justify-center gap-2">
            <x-ui.button variant="secondary" :href="route('learn.resume', $course)">Back to course</x-ui.button>
            <x-ui.button variant="ghost" :href="route('attempts.history', [$course, $assessment])">All attempts</x-ui.button>
        </div>
    </div>
</x-learn-layout>
