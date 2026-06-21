@php
    use App\Enums\QuestionType;

    // Render a sub-question response as readable text for the grader.
    $renderResponse = function ($question, $response) {
        if ($question->type === QuestionType::Essay) {
            return is_string($response) ? $response : '';
        }
        if ($question->type->hasOptions()) {
            $byId = collect($question->options())->keyBy('id');
            $ids = is_array($response) ? $response : [$response];
            return collect($ids)->map(fn ($id) => $byId[$id]['text'] ?? $id)->implode(', ');
        }
        if ($question->type === QuestionType::FillBlank) {
            return is_array($response) ? implode(', ', $response) : (string) $response;
        }
        return is_scalar($response) ? (string) $response : json_encode($response);
    };
@endphp

<x-app-layout :title="'Grade — '.$attempt->assessment->title">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('grading.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to queue
        </a>
        <h2 class="mt-2 font-display text-2xl font-semibold text-ink">{{ $attempt->assessment->title }}</h2>
        <p class="mt-1 text-sm text-ink/60">{{ $attempt->user->name }} · attempt {{ $attempt->attempt_number }}</p>

        @if ($items->isEmpty())
            <div class="mt-6">
                <x-ui.empty-state icon="check-circle" title="Nothing to grade"
                    description="This attempt has no outstanding written answers." />
            </div>
        @else
            <form method="POST" action="{{ route('grading.update', $attempt) }}" class="mt-6 space-y-6">
                @csrf @method('PUT')

                @foreach ($items as $item)
                    @php $answer = $item['answer']; $question = $item['question']; @endphp
                    <x-ui.card>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-ink/45">{{ $question->type->label() }}</span>
                            <span class="text-sm text-ink/50">out of {{ rtrim(rtrim(number_format($item['max'], 2), '0'), '.') }} pts</span>
                        </div>

                        <x-ui.prose class="mt-2 text-sm" :html="$question->prompt" />

                        @if ($question->essayGuidance())
                            <div class="mt-3 rounded-lg border-l-2 border-gold/40 bg-gold/5 p-3 text-sm">
                                <p class="text-xs font-medium text-ink/50">Your grading guidance</p>
                                <p class="mt-1 text-ink/75">{{ $question->essayGuidance() }}</p>
                            </div>
                        @endif

                        {{-- Student's answer --}}
                        <div class="mt-4">
                            <p class="text-xs font-medium text-ink/50">Student's answer</p>
                            @if ($question->type->isScenario())
                                @if ($item['objective_hint'])
                                    <p class="mt-1 text-xs text-green">Objective parts auto-scored {{ $item['objective_hint'][0] }} / {{ $item['objective_hint'][1] }} pts.</p>
                                @endif
                                <div class="mt-2 space-y-2">
                                    @foreach ($question->subQuestions() as $si => $sub)
                                        @php $subQ = $question->makeSubQuestion($sub); $subResp = ($item['response'][$sub['id']] ?? null); @endphp
                                        <div class="rounded-lg border border-line bg-surface/40 p-3 text-sm">
                                            <p class="font-medium text-ink/70">Part {{ $si + 1 }} <span class="text-ink/40">({{ $subQ->type->shortLabel() }})</span></p>
                                            <p class="mt-1 whitespace-pre-line text-ink">{{ $renderResponse($subQ, $subResp) ?: '— no answer —' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-1 whitespace-pre-line rounded-lg bg-surface p-3 text-sm text-ink">{{ $renderResponse($question, $item['response']) ?: '— no answer —' }}</div>
                            @endif
                        </div>

                        {{-- Grade inputs --}}
                        <div class="mt-4 grid gap-4 sm:grid-cols-[8rem,1fr]">
                            <div>
                                <label for="points_{{ $answer->id }}" class="block text-sm font-medium text-ink">Points</label>
                                <input id="points_{{ $answer->id }}" name="grades[{{ $answer->id }}][points]" type="number"
                                       min="0" max="{{ $item['max'] }}" step="0.5" required value="0"
                                       class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            </div>
                            <div>
                                <label for="feedback_{{ $answer->id }}" class="block text-sm font-medium text-ink">Feedback <span class="font-normal text-ink/40">(optional)</span></label>
                                <textarea id="feedback_{{ $answer->id }}" name="grades[{{ $answer->id }}][feedback]" rows="3"
                                          class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                          placeholder="What was strong, what to improve…"></textarea>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach

                <div class="flex justify-end gap-2">
                    <x-ui.button variant="ghost" :href="route('grading.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save grades &amp; finalise</x-ui.button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
