@php
    use App\Enums\ReviewPolicy;
    use App\Enums\QuestionDifficulty;
    use App\Models\Question;

    $base = url('manage/courses/'.$course->slug.'/assessments/'.$assessment->slug);

    $selected = $assessment->questions->map(fn ($q) => [
        'id' => $q->id,
        'points_override' => $q->pivot->points_override !== null ? (float) $q->pivot->points_override : null,
    ])->values();

    $rules = $assessment->poolRules->map(function ($r) {
        $available = Question::where('category_id', $r->category_id)
            ->when($r->difficulty, fn ($q) => $q->where('difficulty', $r->difficulty->value))
            ->count();
        return [
            'id' => $r->id,
            'category_id' => $r->category_id,
            'difficulty' => $r->difficulty?->value ?? '',
            'count' => $r->count,
            'available' => $available,
        ];
    })->values();

    $config = [
        'base' => $base,
        'mode' => $assessment->selection_mode->value,
        'selected' => $selected,
        'bank' => $bank,
        'rules' => $rules,
        'categories' => $bankCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
        'publishErrors' => $publishErrors,
    ];

    $fmtDt = fn ($dt) => $dt?->format('Y-m-d\TH:i');
@endphp

<x-app-layout :title="'Builder — '.$assessment->title">
    <div class="mx-auto max-w-5xl" x-data="assessmentBuilder(@js($config))" x-init="init()">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to {{ $course->title }}
                </a>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $assessment->title }}</h2>
                    <x-ui.badge :variant="$assessment->status->badge()">{{ $assessment->status->label() }}</x-ui.badge>
                    <x-ui.badge variant="neutral">{{ $assessment->placement->label() }}</x-ui.badge>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" size="sm" :href="route('assessments.preview', [$course, $assessment])" target="_blank">
                    <x-ui.icon name="eye" class="h-4 w-4" /> Preview
                </x-ui.button>
                @if ($assessment->isPublished())
                    <form method="POST" action="{{ route('assessments.unpublish', [$course, $assessment]) }}" data-confirm>
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm">Unpublish</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('assessments.publish', [$course, $assessment]) }}">
                        @csrf
                        <x-ui.button type="submit" size="sm" x-bind:disabled="publishErrors.length > 0"
                                     x-bind:title="publishErrors.length ? publishErrors[0] : 'Publish this assessment'">
                            <x-ui.icon name="check" class="h-4 w-4" /> Publish
                        </x-ui.button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Publish checklist --}}
        <div x-show="publishErrors.length > 0" x-cloak class="mt-5 rounded-xl border border-gold/40 bg-gold/10 p-4">
            <p class="text-sm font-medium text-ink">Before publishing:</p>
            <ul class="mt-1.5 list-disc space-y-1 pl-5 text-sm text-ink/75">
                <template x-for="err in publishErrors" :key="err"><li x-text="err"></li></template>
            </ul>
        </div>

        {{-- Tabs --}}
        <div class="mt-6 border-b border-line">
            <nav class="-mb-px flex gap-6" aria-label="Builder sections">
                <button type="button" @click="tab = 'content'"
                        :class="tab === 'content' ? 'border-crimson text-crimson' : 'border-transparent text-ink/60 hover:text-ink'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium focus-ring rounded-t">Questions</button>
                <button type="button" @click="tab = 'settings'"
                        :class="tab === 'settings' ? 'border-crimson text-crimson' : 'border-transparent text-ink/60 hover:text-ink'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium focus-ring rounded-t">Settings</button>
            </nav>
        </div>

        {{-- CONTENT TAB --}}
        <div x-show="tab === 'content'" class="mt-6">
            @include('assessments.partials._builder_content')
        </div>

        {{-- SETTINGS TAB --}}
        <div x-show="tab === 'settings'" x-cloak class="mt-6">
            <form method="POST" action="{{ route('assessments.update', [$course, $assessment]) }}" class="space-y-6">
                @csrf @method('PUT')

                <x-ui.card>
                    <div class="space-y-5">
                        <div>
                            <label for="title" class="block text-sm font-medium text-ink">Title</label>
                            <input id="title" name="title" value="{{ old('title', $assessment->title) }}" required
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </div>
                        <x-ui.rich-editor name="instructions" label="Instructions" profile="basic"
                                          :value="old('instructions', $assessment->instructions)" :height="140" />
                        <div>
                            <label for="selection_mode" class="block text-sm font-medium text-ink">Question selection</label>
                            <select id="selection_mode" name="selection_mode" x-model="mode"
                                    class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson sm:max-w-xs">
                                <option value="fixed">Fixed — the same questions for everyone</option>
                                <option value="pooled">Pooled — random questions per rules</option>
                            </select>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <h3 class="font-display font-semibold text-ink">Scoring &amp; attempts</h3>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="passing_score" class="block text-sm font-medium text-ink">Passing score (%)</label>
                            <input id="passing_score" name="passing_score" type="number" min="0" max="100"
                                   value="{{ old('passing_score', $assessment->passing_score) }}"
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </div>
                        <div>
                            <label for="max_attempts" class="block text-sm font-medium text-ink">Max attempts <span class="text-ink/40">(blank = unlimited)</span></label>
                            <input id="max_attempts" name="max_attempts" type="number" min="1"
                                   value="{{ old('max_attempts', $assessment->max_attempts) }}"
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </div>
                        <div>
                            <label for="time_limit_minutes" class="block text-sm font-medium text-ink">Time limit (minutes) <span class="text-ink/40">(blank = untimed)</span></label>
                            <input id="time_limit_minutes" name="time_limit_minutes" type="number" min="1"
                                   value="{{ old('time_limit_minutes', $assessment->time_limit_minutes) }}"
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </div>
                        <div class="flex items-center gap-2 pt-7">
                            <input id="is_required" name="is_required" type="checkbox" value="1" @checked(old('is_required', $assessment->is_required))
                                   class="rounded border-line text-crimson focus:ring-crimson">
                            <label for="is_required" class="text-sm text-ink/80">Required — counts toward course progress</label>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <h3 class="font-display font-semibold text-ink">Availability window <span class="font-normal text-ink/40">(optional)</span></h3>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="available_from" class="block text-sm font-medium text-ink">Opens</label>
                            <input id="available_from" name="available_from" type="datetime-local"
                                   value="{{ old('available_from', $fmtDt($assessment->available_from)) }}"
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </div>
                        <div>
                            <label for="available_until" class="block text-sm font-medium text-ink">Closes</label>
                            <input id="available_until" name="available_until" type="datetime-local"
                                   value="{{ old('available_until', $fmtDt($assessment->available_until)) }}"
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('available_until')" class="mt-2" />
                </x-ui.card>

                <x-ui.card>
                    <h3 class="font-display font-semibold text-ink">Presentation &amp; review</h3>
                    <div class="mt-4 space-y-4">
                        <label class="flex items-center gap-2 text-sm text-ink/80">
                            <input name="shuffle_questions" type="checkbox" value="1" @checked(old('shuffle_questions', $assessment->shuffle_questions)) class="rounded border-line text-crimson focus:ring-crimson">
                            Shuffle question order
                        </label>
                        <label class="flex items-center gap-2 text-sm text-ink/80">
                            <input name="shuffle_options" type="checkbox" value="1" @checked(old('shuffle_options', $assessment->shuffle_options)) class="rounded border-line text-crimson focus:ring-crimson">
                            Shuffle answer options
                        </label>
                        <div>
                            <label for="review_policy" class="block text-sm font-medium text-ink">When can students review answers?</label>
                            <select id="review_policy" name="review_policy"
                                    class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson sm:max-w-md">
                                @foreach (ReviewPolicy::cases() as $p)
                                    <option value="{{ $p->value }}" @selected(old('review_policy', $assessment->review_policy->value) === $p->value)>{{ $p->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-ink/80">
                            <input name="show_explanations" type="checkbox" value="1" @checked(old('show_explanations', $assessment->show_explanations)) class="rounded border-line text-crimson focus:ring-crimson">
                            Show explanations during review
                        </label>
                    </div>
                </x-ui.card>

                <div class="flex justify-end">
                    <x-ui.button type="submit">Save settings</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
