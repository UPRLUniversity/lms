@php
    use App\Enums\QuestionType;
    use App\Enums\QuestionDifficulty;

    $editing = $question !== null;
    $action = $editing ? route('questions.update', [$course, $question]) : route('questions.store', $course);

    $config = [
        'type' => old('type', $type->value),
        'points' => old('points', $question?->points ?? 1),
        'question' => $editing ? [
            'type' => $question->type->value,
            'payload' => $question->payload,
        ] : null,
    ];

    // Sub-question types offered in the scenario composer.
    $subTypes = [
        QuestionType::McqSingle, QuestionType::McqMulti, QuestionType::TrueFalse,
        QuestionType::FillBlank, QuestionType::Essay,
    ];
@endphp

<x-app-layout :title="$editing ? 'Edit question' : 'New question'">
    <div class="mx-auto max-w-3xl" x-data="questionEditor(@js($config))">
        <a href="{{ route('questions.index', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to question bank
        </a>
        <h2 class="mt-2 font-display text-2xl font-semibold text-ink">{{ $editing ? 'Edit question' : 'New question' }}</h2>

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-6" @submit="onSubmit()">
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="type" :value="type">

            {{-- Meta --}}
            <x-ui.card>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="qtype" class="block text-sm font-medium text-ink">Type</label>
                        <select id="qtype" x-model="type"
                                class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach (QuestionType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="difficulty" class="block text-sm font-medium text-ink">Difficulty</label>
                        <select id="difficulty" name="difficulty"
                                class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach (QuestionDifficulty::cases() as $d)
                                <option value="{{ $d->value }}" @selected(old('difficulty', $question?->difficulty->value) === $d->value)>{{ $d->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="points" class="block text-sm font-medium text-ink">
                            Points
                            <span class="font-normal text-ink/40" x-show="type === 'scenario'">(auto)</span>
                        </label>
                        <input id="points" name="points" type="number" min="0" step="0.5"
                               x-model="points"
                               :readonly="type === 'scenario'"
                               x-effect="if (type === 'scenario') points = scenarioPoints"
                               class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </div>
                </div>

                <div class="mt-5">
                    <label for="category_id" class="block text-sm font-medium text-ink">Category</label>
                    <select id="category_id" name="category_id"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson sm:max-w-xs">
                        <option value="">Uncategorised</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('category_id', $question?->category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-ink/50">Categories let you pool questions for randomised exams.</p>
                </div>
            </x-ui.card>

            {{-- Prompt --}}
            <x-ui.card>
                <x-ui.rich-editor name="prompt" label="Question prompt" :value="old('prompt', $question?->prompt)"
                                  required hint="The scenario stem, for a scenario question." :height="200" />
                <x-input-error :messages="$errors->get('prompt')" class="mt-2" />
            </x-ui.card>

            {{-- Type-specific payload --}}
            <x-ui.card>
                {{-- MCQ (single/multi) --}}
                <template x-if="type === 'mcq_single' || type === 'mcq_multi'">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-display font-semibold text-ink">Options</h3>
                            <span class="text-xs text-ink/50" x-text="type === 'mcq_single' ? 'Mark one correct answer' : 'Mark all correct answers'"></span>
                        </div>
                        <template x-for="(opt, i) in options" :key="opt.key">
                            <div class="flex items-center gap-2">
                                <template x-if="type === 'mcq_single'">
                                    <button type="button" @click="setSingleCorrect(i)" :aria-pressed="opt.is_correct"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border focus-ring"
                                            :class="opt.is_correct ? 'border-success bg-success/10 text-success' : 'border-line text-ink/30'">
                                        <x-ui.icon name="check" class="h-4 w-4" />
                                    </button>
                                </template>
                                <template x-if="type === 'mcq_multi'">
                                    <button type="button" @click="opt.is_correct = !opt.is_correct" :aria-pressed="opt.is_correct"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border focus-ring"
                                            :class="opt.is_correct ? 'border-success bg-success/10 text-success' : 'border-line text-ink/30'">
                                        <x-ui.icon name="check" class="h-4 w-4" />
                                    </button>
                                </template>
                                <input type="text" x-model="opt.text" :name="`payload[options][${i}][text]`" required
                                       placeholder="Option text"
                                       class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <input type="hidden" :name="`payload[options][${i}][is_correct]`" :value="opt.is_correct ? 1 : 0">
                                <button type="button" @click="removeOption(i)" x-show="options.length > 2"
                                        class="rounded-lg p-2 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove option">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="addOption()" class="inline-flex items-center gap-1.5 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Add option
                        </button>
                    </div>
                </template>

                {{-- True / False --}}
                <template x-if="type === 'true_false'">
                    <div class="space-y-3">
                        <h3 class="font-display font-semibold text-ink">Correct answer</h3>
                        <div class="flex gap-3">
                            <button type="button" @click="tf_answer = true"
                                    class="flex-1 rounded-xl border px-4 py-3 text-sm font-medium focus-ring"
                                    :class="tf_answer ? 'border-success bg-success/10 text-success' : 'border-line text-ink/60'">True</button>
                            <button type="button" @click="tf_answer = false"
                                    class="flex-1 rounded-xl border px-4 py-3 text-sm font-medium focus-ring"
                                    :class="!tf_answer ? 'border-crimson bg-crimson/10 text-crimson' : 'border-line text-ink/60'">False</button>
                        </div>
                        <input type="hidden" name="payload[answer]" :value="tf_answer ? 1 : 0">
                    </div>
                </template>

                {{-- Fill in the blank --}}
                <template x-if="type === 'fill_blank'">
                    <div class="space-y-3">
                        <h3 class="font-display font-semibold text-ink">Accepted answers</h3>
                        <p class="text-xs text-ink/50">Any of these counts as correct.</p>
                        <template x-for="(ans, i) in accepted" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="accepted[i]" :name="`payload[accepted][${i}]`" required
                                       placeholder="Accepted answer"
                                       class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <button type="button" @click="removeAccepted(i)" x-show="accepted.length > 1"
                                        class="rounded-lg p-2 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove answer">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="addAccepted()" class="inline-flex items-center gap-1.5 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Add accepted answer
                        </button>
                        <label class="mt-2 flex items-center gap-2 text-sm text-ink/70">
                            <input type="checkbox" x-model="case_insensitive" class="rounded border-line text-crimson focus:ring-crimson">
                            <input type="hidden" name="payload[case_insensitive]" :value="case_insensitive ? 1 : 0">
                            Ignore capitalisation
                        </label>
                    </div>
                </template>

                {{-- Matching --}}
                <template x-if="type === 'matching'">
                    <div class="space-y-3">
                        <h3 class="font-display font-semibold text-ink">Matching pairs</h3>
                        <p class="text-xs text-ink/50">Students match each left item to its right item. Rights are shuffled when presented.</p>
                        <template x-for="(pair, i) in pairs" :key="pair.key">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="pair.left" :name="`payload[pairs][${i}][left]`" required placeholder="Left"
                                       class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <x-ui.icon name="arrows-right-left" class="h-5 w-5 shrink-0 text-ink/30" />
                                <input type="text" x-model="pair.right" :name="`payload[pairs][${i}][right]`" required placeholder="Right"
                                       class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <button type="button" @click="removePair(i)" x-show="pairs.length > 2"
                                        class="rounded-lg p-2 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove pair">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="addPair()" class="inline-flex items-center gap-1.5 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Add pair
                        </button>
                    </div>
                </template>

                {{-- Essay --}}
                <template x-if="type === 'essay'">
                    <div class="space-y-2">
                        <label for="guidance" class="block font-display font-semibold text-ink">Grading guidance <span class="font-normal text-ink/50">(only you see this)</span></label>
                        <textarea id="guidance" name="payload[guidance]" rows="4" x-model="guidance"
                                  placeholder="What a strong answer should contain…"
                                  class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"></textarea>
                    </div>
                </template>

                {{-- Scenario --}}
                <template x-if="type === 'scenario'">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-display font-semibold text-ink">Sub-questions</h3>
                            <span class="text-xs text-ink/50">Total: <span x-text="scenarioPoints"></span> pts</span>
                        </div>
                        <template x-for="(sub, si) in subs" :key="sub.key">
                            <div class="rounded-xl border border-line bg-surface/40 p-4 space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-ink/50" x-text="`Part ${si + 1}`"></span>
                                    <select x-model="sub.type" @change="changeSubType(sub)" :name="`payload[sub_questions][${si}][type]`"
                                            class="rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                        @foreach ($subTypes as $t)
                                            <option value="{{ $t->value }}">{{ $t->shortLabel() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="0" step="0.5" x-model="sub.points" :name="`payload[sub_questions][${si}][points]`"
                                           class="w-20 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson" aria-label="Points">
                                    <span class="text-xs text-ink/40">pts</span>
                                    <button type="button" @click="removeSub(si)" x-show="subs.length > 1"
                                            class="ml-auto rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove part">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>

                                <textarea x-model="sub.prompt" :name="`payload[sub_questions][${si}][prompt]`" rows="2" required
                                          placeholder="Sub-question prompt"
                                          class="block w-full rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson"></textarea>

                                {{-- sub options --}}
                                <template x-if="sub.type === 'mcq_single' || sub.type === 'mcq_multi'">
                                    <div class="space-y-2">
                                        <template x-for="(opt, oi) in sub.options" :key="opt.key">
                                            <div class="flex items-center gap-2">
                                                <button type="button"
                                                        @click="sub.type === 'mcq_single' ? setSubSingleCorrect(sub, oi) : (opt.is_correct = !opt.is_correct)"
                                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border focus-ring"
                                                        :class="opt.is_correct ? 'border-success bg-success/10 text-success' : 'border-line text-ink/30'">
                                                    <x-ui.icon name="check" class="h-3.5 w-3.5" />
                                                </button>
                                                <input type="text" x-model="opt.text" :name="`payload[sub_questions][${si}][payload][options][${oi}][text]`" required
                                                       placeholder="Option" class="block w-full rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                                <input type="hidden" :name="`payload[sub_questions][${si}][payload][options][${oi}][is_correct]`" :value="opt.is_correct ? 1 : 0">
                                                <button type="button" @click="removeSubOption(sub, oi)" x-show="sub.options.length > 2"
                                                        class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove option">
                                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </template>
                                        <button type="button" @click="addSubOption(sub)" class="text-xs font-medium text-crimson hover:text-crimson-dark focus-ring rounded">+ Add option</button>
                                    </div>
                                </template>

                                <template x-if="sub.type === 'true_false'">
                                    <div class="flex gap-2">
                                        <button type="button" @click="sub.tf_answer = true"
                                                class="flex-1 rounded-lg border px-3 py-2 text-sm focus-ring"
                                                :class="sub.tf_answer ? 'border-success bg-success/10 text-success' : 'border-line text-ink/60'">True</button>
                                        <button type="button" @click="sub.tf_answer = false"
                                                class="flex-1 rounded-lg border px-3 py-2 text-sm focus-ring"
                                                :class="!sub.tf_answer ? 'border-crimson bg-crimson/10 text-crimson' : 'border-line text-ink/60'">False</button>
                                        <input type="hidden" :name="`payload[sub_questions][${si}][payload][answer]`" :value="sub.tf_answer ? 1 : 0">
                                    </div>
                                </template>

                                <template x-if="sub.type === 'fill_blank'">
                                    <div class="space-y-2">
                                        <template x-for="(ans, ai) in sub.accepted" :key="ai">
                                            <div class="flex items-center gap-2">
                                                <input type="text" x-model="sub.accepted[ai]" :name="`payload[sub_questions][${si}][payload][accepted][${ai}]`" required
                                                       placeholder="Accepted answer" class="block w-full rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                                <button type="button" @click="removeSubAccepted(sub, ai)" x-show="sub.accepted.length > 1"
                                                        class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove">
                                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </template>
                                        <button type="button" @click="addSubAccepted(sub)" class="text-xs font-medium text-crimson hover:text-crimson-dark focus-ring rounded">+ Add answer</button>
                                        <input type="hidden" :name="`payload[sub_questions][${si}][payload][case_insensitive]`" :value="sub.case_insensitive ? 1 : 0">
                                    </div>
                                </template>

                                <template x-if="sub.type === 'essay'">
                                    <p class="text-xs text-ink/50">Graded by hand after submission.</p>
                                </template>
                            </div>
                        </template>
                        <button type="button" @click="addSub()" class="inline-flex items-center gap-1.5 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                            <x-ui.icon name="plus" class="h-4 w-4" /> Add sub-question
                        </button>
                    </div>
                </template>

                <x-input-error :messages="$errors->get('payload')" class="mt-3" />
                @foreach ($errors->get('payload.*') as $msgs) <x-input-error :messages="$msgs" class="mt-1" /> @endforeach
            </x-ui.card>

            {{-- Explanation --}}
            <x-ui.card>
                <x-ui.rich-editor name="explanation" label="Explanation (shown after submission)" profile="basic"
                                  :value="old('explanation', $question?->explanation)" :height="160" />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="ghost" :href="route('questions.index', $course)">Cancel</x-ui.button>
                <x-ui.button type="submit">{{ $editing ? 'Save question' : 'Add to bank' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
