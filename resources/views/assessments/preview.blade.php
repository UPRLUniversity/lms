@php use App\Enums\QuestionType; @endphp

<x-app-layout :title="'Preview — '.$assessment->title">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('assessments.edit', [$course, $assessment]) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to builder
        </a>

        <div class="mt-4 rounded-xl border border-gold/40 bg-gold/10 px-4 py-3 text-sm text-ink/75">
            <x-ui.icon name="eye" class="mr-1 inline h-4 w-4" /> Preview — this is how the assessment appears to a student. No attempt is recorded.
        </div>

        <h1 class="mt-5 font-display text-2xl font-semibold text-ink">{{ $assessment->title }}</h1>
        @if ($assessment->instructions)
            <x-ui.prose class="mt-3 text-sm" :html="$assessment->instructions" />
        @endif

        @if ($assessment->isPooled())
            <div class="mt-6 rounded-xl border border-line bg-card p-5">
                <p class="font-medium text-ink">Pooled assessment</p>
                <p class="mt-1 text-sm text-ink/60">Each student draws a random set from these rules:</p>
                <ul class="mt-3 space-y-1.5 text-sm text-ink/75">
                    @foreach ($assessment->poolRules as $rule)
                        <li class="flex items-center gap-2">
                            <x-ui.icon name="flask" class="h-4 w-4 text-ink/40" />
                            {{ $rule->count }} {{ $rule->difficulty?->label() ?? 'any' }} question(s) from {{ $rule->category?->name ?? '—' }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <ol class="mt-6 space-y-4">
                @foreach ($assessment->questions as $i => $question)
                    <li class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-ink/45">Question {{ $i + 1 }}</span>
                            <span class="text-xs text-ink/45">{{ $question->type->shortLabel() }}</span>
                        </div>
                        <x-ui.prose class="mt-2 text-sm" :html="$question->prompt" />

                        @switch($question->type)
                            @case(QuestionType::McqSingle)
                            @case(QuestionType::McqMulti)
                            @case(QuestionType::TrueFalse)
                                <ul class="mt-3 space-y-2">
                                    @foreach ($question->options() as $opt)
                                        <li class="flex items-center gap-3 rounded-lg border border-line p-2.5 text-sm text-ink/80">
                                            <span class="inline-block h-4 w-4 shrink-0 border border-ink/25 {{ $question->type === QuestionType::McqMulti ? 'rounded' : 'rounded-full' }}"></span>
                                            {{ $opt['text'] }}
                                        </li>
                                    @endforeach
                                </ul>
                                @break
                            @case(QuestionType::FillBlank)
                                <input disabled placeholder="Student types an answer" class="mt-3 block w-full rounded-lg border-line bg-surface text-sm text-ink/50">
                                @break
                            @case(QuestionType::Matching)
                                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                    <ul class="space-y-1.5">
                                        @foreach ($question->pairs() as $p)<li class="rounded-lg border border-line p-2 text-ink/80">{{ $p['left'] }}</li>@endforeach
                                    </ul>
                                    <ul class="space-y-1.5">
                                        @foreach (collect($question->pairs())->shuffle() as $p)<li class="rounded-lg border border-line p-2 text-ink/60">{{ $p['right'] }}</li>@endforeach
                                    </ul>
                                </div>
                                @break
                            @case(QuestionType::Essay)
                                <textarea disabled rows="4" placeholder="Student writes a response" class="mt-3 block w-full rounded-lg border-line bg-surface text-sm text-ink/50"></textarea>
                                @break
                            @case(QuestionType::Scenario)
                                <ol class="mt-3 space-y-2">
                                    @foreach ($question->subQuestions() as $si => $sub)
                                        <li class="rounded-lg border border-line bg-surface/40 p-3 text-sm">
                                            <span class="font-medium text-ink/70">Part {{ $si + 1 }}.</span> {!! $sub['prompt'] !!}
                                        </li>
                                    @endforeach
                                </ol>
                                @break
                        @endswitch
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-app-layout>
