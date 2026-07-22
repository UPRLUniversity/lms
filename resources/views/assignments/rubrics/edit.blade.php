@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Rubric $rubric */
    $isNew = ! $rubric->exists;

    // Initial grid state for Alpine: existing criteria, or one starter row.
    $initial = $rubric->exists
        ? $rubric->criteria->map(fn ($c) => [
            'title' => $c->title,
            'description' => (string) $c->description,
            'levels' => collect($c->levels)->map(fn ($l) => [
                'label' => (string) ($l['label'] ?? ''),
                'description' => (string) ($l['description'] ?? ''),
                'points' => (float) ($l['points'] ?? 0),
            ])->values()->all(),
        ])->values()->all()
        : [[
            'title' => '',
            'description' => '',
            'levels' => [
                ['label' => 'Excellent', 'description' => '', 'points' => 10],
                ['label' => 'Good', 'description' => '', 'points' => 7],
                ['label' => 'Needs work', 'description' => '', 'points' => 3],
            ],
        ]];
@endphp

<x-app-layout :title="$isNew ? 'New rubric' : $rubric->name">
    <div class="mx-auto max-w-6xl space-y-6"
         x-data="{
            criteria: {{ json_encode($initial) }},
            criterionMax(c) { return c.levels.reduce((m, l) => Math.max(m, Number(l.points) || 0), 0); },
            total() { return this.criteria.reduce((s, c) => s + this.criterionMax(c), 0); },
            fmt(n) { return Number.isInteger(n) ? n : n.toFixed(2).replace(/\.?0+$/, ''); },
            addCriterion() {
                const cols = this.criteria[0]?.levels?.map(l => ({ label: l.label, description: '', points: 0 }))
                    ?? [{ label: 'Excellent', description: '', points: 10 }, { label: 'Good', description: '', points: 7 }];
                this.criteria.push({ title: '', description: '', levels: cols });
            },
            removeCriterion(i) { if (this.criteria.length > 1) this.criteria.splice(i, 1); },
            addLevel(c) { if (c.levels.length < 8) c.levels.push({ label: '', description: '', points: 0 }); },
            removeLevel(c, j) { if (c.levels.length > 2) c.levels.splice(j, 1); },
         }">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('rubrics.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Rubrics
                </a>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $isNew ? 'New rubric' : $rubric->name }}</h2>
                @if (! $isNew && $rubric->assignments_count > 0)
                    <p class="mt-1 text-sm text-ink/60">
                        Used by {{ $rubric->assignments_count }} {{ Str::plural('assignment', $rubric->assignments_count) }}.
                        Existing grades keep the breakdown they were scored with.
                    </p>
                @endif
            </div>
            {{-- Live point math --}}
            <div class="rounded-xl border border-line bg-card px-4 py-2.5 text-right shadow-sm">
                <p class="text-xs text-ink/50">Best possible score</p>
                <p class="font-display text-xl font-semibold text-crimson" x-text="fmt(total()) + ' pts'"></p>
            </div>
        </div>

        <form method="POST"
              action="{{ $isNew ? route('rubrics.store') : route('rubrics.update', $rubric) }}"
              class="space-y-5">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <x-ui.card>
                <label for="name" class="block text-sm font-medium text-ink">Rubric name</label>
                <input id="name" name="name" required maxlength="255" value="{{ old('name', $rubric->name) }}"
                       @disabled(! $canManage)
                       class="mt-1.5 block w-full max-w-lg rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                       placeholder="e.g. Essay rubric — 300 level">
                @error('name')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                @error('criteria')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
            </x-ui.card>

            {{-- The grid: criteria rows × level columns --}}
            <template x-for="(criterion, i) in criteria" :key="i">
                <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                    <div class="flex flex-wrap items-start gap-3 border-b border-line bg-surface/40 p-4">
                        <div class="min-w-0 flex-1">
                            <label class="block text-xs font-medium text-ink/50" :for="`c_${i}_title`">Criterion <span x-text="i + 1"></span></label>
                            <input :id="`c_${i}_title`" x-model="criterion.title" :name="`criteria[${i}][title]`" required maxlength="255"
                                   @disabled(! $canManage)
                                   class="mt-1 block w-full rounded-xl border-line bg-card font-medium text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                   placeholder="e.g. Argument & analysis">
                            <input x-model="criterion.description" :name="`criteria[${i}][description]`" maxlength="2000"
                                   @disabled(! $canManage)
                                   class="mt-2 block w-full rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                   placeholder="What this criterion looks at (optional)">
                        </div>
                        <div class="flex items-center gap-2 pt-5">
                            <span class="text-sm text-ink/50">max <span class="font-semibold text-ink" x-text="fmt(criterionMax(criterion))"></span> pts</span>
                            @if ($canManage)
                                <button type="button" @click="removeCriterion(i)" x-show="criteria.length > 1"
                                        class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove criterion">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Level columns --}}
                    <div class="overflow-x-auto">
                        <div class="flex min-w-max gap-3 p-4">
                            <template x-for="(level, j) in criterion.levels" :key="j">
                                <div class="w-56 shrink-0 rounded-xl border border-line bg-surface/40 p-3">
                                    <div class="flex items-center justify-between gap-1">
                                        <input x-model="level.label" :name="`criteria[${i}][levels][${j}][label]`" required maxlength="100"
                                               @disabled(! $canManage)
                                               class="block w-full rounded-lg border-line bg-card text-sm font-medium text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                               placeholder="Level label">
                                        @if ($canManage)
                                            <button type="button" @click="removeLevel(criterion, j)" x-show="criterion.levels.length > 2"
                                                    class="rounded p-1 text-ink/35 hover:text-crimson focus-ring" aria-label="Remove level">
                                                <x-ui.icon name="x" class="h-3.5 w-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                    <textarea x-model="level.description" :name="`criteria[${i}][levels][${j}][description]`" rows="3" maxlength="2000"
                                              @disabled(! $canManage)
                                              class="mt-2 block w-full rounded-lg border-line bg-card text-xs text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                              placeholder="What earns this level…"></textarea>
                                    <div class="mt-2 flex items-center gap-1.5">
                                        <input x-model.number="level.points" :name="`criteria[${i}][levels][${j}][points]`" type="number" min="0" max="10000" step="0.5" required
                                               @disabled(! $canManage)
                                               class="block w-20 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                        <span class="text-xs text-ink/50">pts</span>
                                    </div>
                                </div>
                            </template>

                            @if ($canManage)
                                <button type="button" @click="addLevel(criterion)" x-show="criterion.levels.length < 8"
                                        class="flex w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-line text-ink/40 transition hover:border-crimson/40 hover:text-crimson focus-ring">
                                    <x-ui.icon name="plus" class="h-5 w-5" />
                                    <span class="text-xs font-medium">Level</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </template>

            @if ($canManage)
                <button type="button" @click="addCriterion()" x-show="criteria.length < 20"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-line px-4 py-2.5 text-sm font-medium text-ink/60 transition hover:border-crimson/40 hover:text-crimson focus-ring">
                    <x-ui.icon name="plus" class="h-4 w-4" /> Add criterion
                </button>

                <div class="flex items-center justify-between gap-2 border-t border-line pt-5">
                    @unless ($isNew)
                        <x-ui.button type="button" variant="ghost" class="text-crimson"
                            onclick="window.uprlConfirm({ title: 'Delete this rubric?', text: 'Assignments using it will simply have no rubric attached; existing grades keep their breakdown.', confirmText: 'Delete rubric' }).then(ok => ok && document.getElementById('delete-rubric').submit())">
                            <x-ui.icon name="trash" class="h-4 w-4" /> Delete
                        </x-ui.button>
                    @endunless
                    <div class="ml-auto flex gap-2">
                        <x-ui.button variant="ghost" :href="route('rubrics.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit">{{ $isNew ? 'Create rubric' : 'Save rubric' }}</x-ui.button>
                    </div>
                </div>
            @endif
        </form>

        @unless ($isNew)
            @if ($canManage)
                <form id="delete-rubric" method="POST" action="{{ route('rubrics.destroy', $rubric) }}" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endif
        @endunless
    </div>
</x-app-layout>
