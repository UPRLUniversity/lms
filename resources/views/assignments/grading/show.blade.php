@php
    /** @var \App\Models\Submission $submission */
    /** @var \App\Models\Assignment $assignment */
    /** @var \App\Models\Rubric|null $rubric */
    /** @var \App\Models\Grade|null $grade */

    $fmtPts = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $hasRubric = $rubric && $rubric->criteria->isNotEmpty();

    // Pre-select existing choices when regrading.
    $priorSelections = collect($grade?->criterion_scores ?? [])
        ->mapWithKeys(fn ($row) => [(string) $row['criterion_id'] => (int) $row['level_index']]);

    $rubricState = $hasRubric ? $rubric->criteria->map(fn ($c) => [
        'id' => $c->id,
        'levels' => collect($c->levels)->map(fn ($l) => (float) ($l['points'] ?? 0))->values()->all(),
        'selected' => $priorSelections->has((string) $c->id) ? $priorSelections[(string) $c->id] : null,
    ])->values() : collect();
@endphp

<x-app-layout :title="'Grade — '.$assignment->title">
    <div class="mx-auto max-w-7xl space-y-5"
         x-data="{
            criteria: {{ json_encode($rubricState) }},
            select(i, j) { this.criteria[i].selected = j; },
            total() {
                return this.criteria.reduce((s, c) => s + (c.selected === null ? 0 : (c.levels[c.selected] ?? 0)), 0);
            },
            allScored() { return this.criteria.every(c => c.selected !== null); },
            fmt(n) { return Number.isInteger(n) ? n : n.toFixed(2).replace(/\.?0+$/, ''); },
         }">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('grading.assignments.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to queue
                </a>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $assignment->title }}</h2>
                    @if ($submission->is_late)
                        <x-ui.badge variant="crimson">Late</x-ui.badge>
                    @endif
                    <x-ui.badge :variant="$submission->status->badge()">{{ $submission->status->label() }}</x-ui.badge>
                </div>
                <p class="mt-1 text-sm text-ink/60">
                    {{ $submission->user->name }} · version {{ $submission->version }} of {{ $versionCount }}
                    · submitted {{ $submission->submitted_at->isoFormat('D MMM YYYY, HH:mm') }}
                    · {{ $assignment->course->title }}
                </p>
            </div>
            <p class="text-sm text-ink/55">{{ $queueCount }} in queue</p>
        </div>

        @if ($submission->isReturned())
            <div class="rounded-xl border border-gold/40 bg-gold/10 p-3.5 text-sm">
                <span class="font-medium text-ink">Previously returned:</span>
                <span class="text-ink/75">{{ $submission->return_note }}</span>
            </div>
        @endif

        {{-- Split view: work beside rubric --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- The student's work --}}
            <div class="space-y-4">
                @if (trim(strip_tags((string) $submission->body)) !== '')
                    <x-ui.card>
                        <h3 class="text-sm font-semibold text-ink/70">Typed answer</h3>
                        <x-ui.prose class="mt-2 text-sm" :html="$submission->body" />
                    </x-ui.card>
                @endif

                @if ($files->isNotEmpty())
                    <x-ui.card>
                        <h3 class="text-sm font-semibold text-ink/70">Files</h3>
                        <ul class="mt-2 space-y-3">
                            @foreach ($files as $file)
                                @php $media = $file['media']; @endphp
                                <li class="overflow-hidden rounded-xl border border-line">
                                    <div class="flex items-center gap-2.5 bg-surface/40 px-3 py-2.5">
                                        <x-ui.icon name="document" class="h-4 w-4 shrink-0 text-ink/40" />
                                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ $media->original_name }}</span>
                                        <span class="shrink-0 text-xs text-ink/45">{{ number_format($media->size_bytes / 1024) }} KB</span>
                                        <a href="{{ route('media.download', $media) }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                                            <x-ui.icon name="download" class="h-4 w-4" /> Download
                                        </a>
                                    </div>
                                    @if ($file['previewUrl'])
                                        @if (str_starts_with((string) $media->mime, 'image/'))
                                            <img src="{{ $file['previewUrl'] }}" alt="Preview of {{ $media->original_name }}" class="max-h-[28rem] w-full bg-ink/5 object-contain">
                                        @else
                                            <iframe src="{{ $file['previewUrl'] }}" title="Preview of {{ $media->original_name }}" class="h-[28rem] w-full bg-ink/5"></iframe>
                                        @endif
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                @if (trim(strip_tags((string) $submission->body)) === '' && $files->isEmpty())
                    <x-ui.empty-state icon="document" title="Nothing attached"
                        description="This version has no typed answer and no files." />
                @endif
            </div>

            {{-- The rubric / grade panel --}}
            <div>
                <form method="POST" action="{{ route('grading.assignments.update', $submission) }}" class="space-y-4">
                    @csrf @method('PUT')

                    <x-ui.card>
                        <div class="flex items-center justify-between">
                            <h3 class="font-display text-lg font-semibold text-ink">{{ $hasRubric ? $rubric->name : 'Score' }}</h3>
                            <div class="text-right">
                                <p class="text-xs text-ink/50">Total</p>
                                @if ($hasRubric)
                                    <p class="font-display text-xl font-semibold text-crimson">
                                        <span x-text="fmt(total())"></span><span class="text-sm text-ink/40"> / {{ $fmtPts($assignment->max_points) }}</span>
                                    </p>
                                @else
                                    <p class="font-display text-xl font-semibold text-ink/40">/ {{ $fmtPts($assignment->max_points) }}</p>
                                @endif
                            </div>
                        </div>

                        @if ($hasRubric)
                            <p class="mt-1 text-xs text-ink/50">Click one level per criterion — the total is computed for you (and re-checked on the server).</p>

                            <div class="mt-4 space-y-4">
                                @foreach ($rubric->criteria as $i => $criterion)
                                    <fieldset>
                                        <legend class="text-sm font-semibold text-ink">{{ $criterion->title }}</legend>
                                        @if ($criterion->description)
                                            <p class="mt-0.5 text-xs text-ink/55">{{ $criterion->description }}</p>
                                        @endif
                                        @php
                                            // Literal class names so Tailwind's scanner sees them.
                                            $levelCols = match (min(count($criterion->levels), 3)) {
                                                3 => 'sm:grid-cols-3',
                                                2 => 'sm:grid-cols-2',
                                                default => '',
                                            };
                                        @endphp
                                        <div class="mt-2 grid gap-2 {{ $levelCols }}">
                                            @foreach ($criterion->levels as $j => $level)
                                                <label class="relative flex cursor-pointer flex-col rounded-xl border p-3 transition focus-within:ring-2 focus-within:ring-crimson"
                                                       :class="criteria[{{ $i }}].selected === {{ $j }} ? 'border-crimson bg-crimson/5' : 'border-line bg-surface/40 hover:border-ink/25'">
                                                    <input type="radio" name="criteria[{{ $criterion->id }}]" value="{{ $j }}"
                                                           class="sr-only" @disabled(! $canGrade)
                                                           @checked($priorSelections->get((string) $criterion->id) === $j)
                                                           @change="select({{ $i }}, {{ $j }})">
                                                    <span class="flex items-center justify-between gap-2">
                                                        <span class="text-sm font-medium text-ink">{{ $level['label'] ?? '' }}</span>
                                                        <span class="shrink-0 text-xs font-semibold"
                                                              :class="criteria[{{ $i }}].selected === {{ $j }} ? 'text-crimson' : 'text-ink/45'">{{ $fmtPts($level['points'] ?? 0) }} pts</span>
                                                    </span>
                                                    @if (! empty($level['description']))
                                                        <span class="mt-1 text-xs leading-snug text-ink/60">{{ $level['description'] }}</span>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4">
                                <label for="points" class="block text-sm font-medium text-ink">Points awarded</label>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <input id="points" name="points" type="number" min="0" max="{{ $assignment->max_points }}" step="0.5"
                                           value="{{ old('points', $grade ? $fmtPts($grade->points_total) : '') }}" @disabled(! $canGrade) required
                                           class="block w-32 rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                    <span class="text-sm text-ink/50">of {{ $fmtPts($assignment->max_points) }}</span>
                                </div>
                            </div>
                        @endif

                        @error('criteria')<p class="mt-2 text-sm text-crimson">{{ $message }}</p>@enderror
                        @error('points')<p class="mt-2 text-sm text-crimson">{{ $message }}</p>@enderror

                        <div class="mt-5 border-t border-line pt-4">
                            @if ($canGrade)
                                <x-ui.rich-editor
                                    name="feedback"
                                    id="grade-feedback"
                                    label="Overall feedback"
                                    profile="basic"
                                    :value="old('feedback', $grade?->feedback)"
                                    :height="180"
                                    placeholder="What was strong, what to improve…" />
                            @elseif ($grade?->feedback)
                                <p class="text-sm font-medium text-ink">Overall feedback</p>
                                <x-ui.prose class="mt-1.5 text-sm" :html="$grade->feedback" />
                            @endif
                        </div>
                    </x-ui.card>

                    @if ($canGrade)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <x-ui.button type="button" variant="ghost" class="text-gold-ink" @click="$dispatch('open-modal', 'return-submission')">
                                <x-ui.icon name="arrow-path" class="h-4 w-4" /> Return for resubmission
                            </x-ui.button>
                            <div class="flex gap-2">
                                {{-- allScored() is vacuously true without a rubric, so the binding is safe either way. --}}
                                <x-ui.button type="submit" variant="secondary" x-bind:disabled="! allScored()">
                                    {{ $grade ? 'Update grade' : 'Save grade' }}
                                </x-ui.button>
                                <x-ui.button type="submit" name="and_next" value="1" x-bind:disabled="! allScored()">
                                    <x-ui.icon name="arrow-right" class="h-4 w-4" /> Grade &amp; next
                                </x-ui.button>
                            </div>
                        </div>
                    @endif
                </form>

                {{-- Return-for-resubmission modal (its own form, outside the grade form) --}}
                @if ($canGrade)
                    <x-ui.modal name="return-submission" title="Return for resubmission">
                        <form method="POST" action="{{ route('grading.assignments.return', $submission) }}" class="space-y-4">
                            @csrf
                            <p class="text-sm text-ink/70">
                                The student will be asked for a new version. Tell them what to improve — the note is required and shown on their assignment page.
                            </p>
                            <div>
                                <label for="return-note" class="block text-sm font-medium text-ink">Note to the student</label>
                                <textarea id="return-note" name="note" rows="4" required maxlength="5000"
                                          class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                          placeholder="e.g. Solid start — please add citations for the case study section and resubmit.">{{ old('note') }}</textarea>
                                @error('note')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex justify-end gap-2 pt-1">
                                <x-ui.button type="button" variant="ghost" @click="$dispatch('close-modal', 'return-submission')">Cancel</x-ui.button>
                                <x-ui.button type="submit">Return to student</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
