@php
    /** @var \App\Models\GradeScale $scale */
    $isNew = ! $scale->exists;

    $initialBands = $scale->exists
        ? $scale->bands->map(fn ($b) => [
            'label' => $b->label,
            'grade_point' => (float) $b->grade_point,
            'is_pass' => (bool) $b->is_pass,
            'min_percent' => $b->min_percent,
            'max_percent' => $b->max_percent,
            'color' => $b->color,
        ])->values()->all()
        : [
            ['label' => 'A', 'grade_point' => 5, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 4, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
            ['label' => 'C', 'grade_point' => 3, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
            ['label' => 'F', 'grade_point' => 0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
        ];

    $colors = ['success' => 'Green', 'gold' => 'Gold', 'crimson' => 'Crimson', 'ink' => 'Ink', 'neutral' => 'Neutral'];
@endphp

<x-app-layout :title="$isNew ? 'New grade scale' : $scale->name">
    <div class="mx-auto max-w-5xl space-y-6"
         x-data="{
            bands: {{ json_encode($initialBands) }},
            scaleLimit: {{ $scale->scale_limit ?? 5.00 }},
            displayMode: '{{ old('display_mode', $scale->display_mode?->value ?? 'both') }}',
            showScaleLimit: {{ old('show_scale_limit', $scale->show_scale_limit ?? true) ? 'true' : 'false' }},
            separator: @js(old('separator', $scale->separator ?? '/')),
            tryScore: 75,

            addBand() {
                if (this.bands.length < 15) this.bands.push({ label: '', grade_point: 0, is_pass: false, min_percent: 0, max_percent: 0, color: 'neutral' });
            },
            removeBand(i) { if (this.bands.length > 2) this.bands.splice(i, 1); },

            sortedAsc() {
                return [...this.bands].sort((a, b) => (Number(a.min_percent) || 0) - (Number(b.min_percent) || 0));
            },

            // The pass mark is DERIVED from the lowest passing band, never stored — so it
            // can't drift from the bands it describes. Null while no band passes.
            passMark() {
                const passing = this.bands.filter(b => b.is_pass);
                return passing.length ? Math.min(...passing.map(b => Number(b.min_percent) || 0)) : null;
            },

            // Compressed coverage runs across 0–100 for the visual bar: 'ok' (one band),
            // 'overlap' (2+ bands) or 'gap' (none) — mirrors GradeBandValidator server-side.
            segments() {
                const cover = new Array(101).fill(0);
                this.bands.forEach(b => {
                    let min = Math.max(0, Math.min(100, Number(b.min_percent) || 0));
                    let max = Math.max(0, Math.min(100, Number(b.max_percent) || 0));
                    if (min > max) [min, max] = [max, min];
                    for (let p = min; p <= max; p++) cover[p]++;
                });
                const runs = [];
                let start = 0;
                for (let p = 1; p <= 101; p++) {
                    if (p === 101 || cover[p] !== cover[start]) {
                        runs.push({ start, end: p - 1, state: cover[start] === 0 ? 'gap' : (cover[start] > 1 ? 'overlap' : 'ok') });
                        start = p;
                    }
                }
                return runs;
            },

            messages() {
                const msgs = [];
                if (this.bands.length < 2) msgs.push('Add at least two bands.');

                const labels = this.bands.map(b => (b.label || '').trim().toLowerCase()).filter(l => l !== '');
                if (new Set(labels).size !== labels.length) msgs.push('Band labels must be unique.');

                this.segments().forEach(seg => {
                    if (seg.state === 'gap') msgs.push(`Coverage gap: ${seg.start}–${seg.end}% is not covered by any band.`);
                    if (seg.state === 'overlap') msgs.push(`Overlap: ${seg.start}–${seg.end}% is covered by more than one band.`);
                });

                const sorted = this.sortedAsc();
                for (let i = 1; i < sorted.length; i++) {
                    if ((Number(sorted[i].grade_point) || 0) <= (Number(sorted[i - 1].grade_point) || 0)) {
                        msgs.push(`“${sorted[i].label || '?'}” must carry a higher grade point than “${sorted[i - 1].label || '?'}”.`);
                    }
                }

                this.bands.forEach(b => {
                    if ((Number(b.grade_point) || 0) > (Number(this.scaleLimit) || 0)) {
                        msgs.push(`“${b.label || '?'}”'s grade point exceeds the scale limit.`);
                    }
                });

                // Pass invariants — mirrors GradeBandValidator server-side.
                if (!this.bands.some(b => b.is_pass)) {
                    msgs.push('Mark at least one band as a pass — otherwise no student on this scale could ever pass.');
                }
                if (!this.bands.some(b => !b.is_pass)) {
                    msgs.push('Mark at least one band as a fail — otherwise no student on this scale could ever fail.');
                }

                let seenPass = false;
                sorted.forEach(b => {
                    if (b.is_pass) { seenPass = true; return; }
                    if (seenPass) {
                        msgs.push(`“${b.label || '?'}” is marked as a fail but a lower band passes. Passing bands must be the top of the scale.`);
                    }
                });

                return msgs;
            },

            bandFor(score) {
                const s = Math.max(0, Math.min(100, Math.round(Number(score) || 0)));
                return this.bands.find(b => s >= (Number(b.min_percent) || 0) && s <= (Number(b.max_percent) || 0)) ?? null;
            },

            fmtPoint(n) { return (Number(n) || 0).toFixed(1); },

            previewLine(score) {
                const parts = [Math.round(Number(score) || 0) + '%'];
                const band = this.bandFor(score);
                if (band) {
                    if (this.displayMode !== 'points') parts.push(band.label || '?');
                    if (this.displayMode !== 'letter') {
                        parts.push(this.showScaleLimit ? `${this.fmtPoint(band.grade_point)}${this.separator}${this.fmtPoint(this.scaleLimit)}` : this.fmtPoint(band.grade_point));
                    }
                }
                return parts.join(' · ');
            },
         }">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('admin.grade-scales.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Grade scales
                </a>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ $isNew ? 'New grade scale' : $scale->name }}</h2>
                @if (! $isNew && $scale->isArchived())
                    <p class="mt-1 text-sm text-gold-ink">This scale is archived — it won't appear for new course selections.</p>
                @endif
            </div>
        </div>

        @if ($errors->keys() && collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'bands.')))
            <div class="rounded-xl border border-crimson/30 bg-crimson/5 px-4 py-3 text-sm text-crimson">
                Check the highlighted band fields — every band needs a label, a min/max %, and a grade point.
            </div>
        @endif

        <form method="POST" action="{{ $isNew ? route('admin.grade-scales.store') : route('admin.grade-scales.update', $scale) }}" class="space-y-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            {{-- Settings --}}
            <x-ui.card>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="name" label="Scale name" required :value="old('name', $scale->name)" />
                    <x-ui.field name="scale_limit" label="Scale limit" required hint="The maximum possible grade point, e.g. 5.00 or 4.00.">
                        <input id="scale_limit" name="scale_limit" type="number" step="0.01" min="0.01" max="100"
                               x-model.number="scaleLimit"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>
                </div>
                @error('scale_limit')<p class="mt-2 text-sm text-crimson">{{ $message }}</p>@enderror
                @error('name')<p class="mt-2 text-sm text-crimson">{{ $message }}</p>@enderror

                <label class="mt-4 flex items-center gap-2 text-sm text-ink">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" class="rounded border-line text-crimson focus:ring-crimson"
                           @checked(old('is_default', $scale->is_default))>
                    Make this the system default
                </label>
            </x-ui.card>

            {{-- Display settings --}}
            <x-ui.card>
                <h3 class="font-display font-semibold text-ink">Display settings</h3>
                <p class="mt-1 text-xs text-ink/60">Presentation only — never affects how anything is scored.</p>

                <div class="mt-4 grid gap-5 sm:grid-cols-3">
                    <x-ui.field name="display_mode" label="Display mode" required>
                        <select id="display_mode" name="display_mode" x-model="displayMode"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach (\App\Enums\GradeDisplayMode::cases() as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="separator" label="Separator" hint='e.g. "/" or "out of"'>
                        <input id="separator" name="separator" x-model="separator" maxlength="10"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    </x-ui.field>

                    <div class="flex items-end pb-1.5">
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="hidden" name="show_scale_limit" value="0">
                            <input type="checkbox" name="show_scale_limit" value="1" x-model="showScaleLimit"
                                   class="rounded border-line text-crimson focus:ring-crimson">
                            Show the scale limit (e.g. "/5.0")
                        </label>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-line bg-surface/50 px-4 py-3 text-sm">
                    <span class="text-ink/50">Preview:</span>
                    <span class="font-medium text-ink" x-text="previewLine(tryScore)"></span>
                </div>
            </x-ui.card>

            {{-- Coverage bar --}}
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-display font-semibold text-ink">Coverage — 0 to 100%</h3>
                    <span class="text-xs text-ink/50">Green = covered once · gold = overlap · crimson = gap</span>
                </div>
                <div class="mt-3 flex h-6 overflow-hidden rounded-lg border border-line">
                    <template x-for="(seg, i) in segments()" :key="i">
                        <div :style="`width: ${(seg.end - seg.start + 1)}%`"
                             :class="{
                                'bg-success/70': seg.state === 'ok',
                                'bg-gold': seg.state === 'overlap',
                                'bg-crimson/70': seg.state === 'gap',
                             }"
                             :title="`${seg.start}–${seg.end}% (${seg.state})`"></div>
                    </template>
                </div>
                <div class="mt-1 flex justify-between text-[11px] text-ink/40">
                    <span>0%</span><span>100%</span>
                </div>

                <template x-if="messages().length">
                    <ul class="mt-3 space-y-1 text-sm text-crimson">
                        <template x-for="(m, i) in messages()" :key="i">
                            <li x-text="m"></li>
                        </template>
                    </ul>
                </template>
                @error('bands')<p class="mt-3 text-sm text-crimson">{{ $message }}</p>@enderror
            </x-ui.card>

            {{-- Band grid --}}
            <x-ui.card :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div>
                        <h3 class="font-display font-semibold text-ink">Bands</h3>
                        <p class="mt-1 text-sm text-ink/60">
                            <template x-if="passMark() !== null">
                                <span><span class="font-medium text-ink">Pass mark:</span> <span x-text="passMark() + '%'"></span> — the lowest band marked as a pass.</span>
                            </template>
                            <template x-if="passMark() === null">
                                <span class="text-crimson">No band is marked as a pass, so nobody on this scale can pass.</span>
                            </template>
                        </p>
                    </div>
                    <button type="button" @click="addBand()" x-show="bands.length < 15"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-line px-3 py-1.5 text-sm font-medium text-ink/60 hover:border-crimson/40 hover:text-crimson focus-ring">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Add band
                    </button>
                </div>
                <div class="overflow-x-auto border-t border-line">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                                <th class="px-4 py-2.5">Label</th>
                                <th class="px-4 py-2.5">Min %</th>
                                <th class="px-4 py-2.5">Max %</th>
                                <th class="px-4 py-2.5">Grade point</th>
                                <th class="px-4 py-2.5">Pass</th>
                                <th class="px-4 py-2.5">Colour</th>
                                <th class="px-4 py-2.5"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <template x-for="(band, i) in bands" :key="i">
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <input x-model="band.label" :name="`bands[${i}][label]`" required maxlength="50"
                                               class="block w-28 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <input x-model.number="band.min_percent" :name="`bands[${i}][min_percent]`" type="number" min="0" max="100" required
                                               class="block w-20 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <input x-model.number="band.max_percent" :name="`bands[${i}][max_percent]`" type="number" min="0" max="100" required
                                               class="block w-20 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <input x-model.number="band.grade_point" :name="`bands[${i}][grade_point]`" type="number" min="0" max="100" step="0.1" required
                                               class="block w-20 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        {{-- A hidden field carries the value so an unticked box still posts "0";
                                             the checkbox itself is unnamed and drives it through Alpine. --}}
                                        <input type="hidden" :name="`bands[${i}][is_pass]`" :value="band.is_pass ? 1 : 0">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" x-model="band.is_pass"
                                                   :aria-label="`${band.label || 'This band'} is a pass`"
                                                   class="rounded border-line text-success focus:ring-success">
                                            <span class="text-xs font-medium"
                                                  :class="band.is_pass ? 'text-success' : 'text-ink/40'"
                                                  x-text="band.is_pass ? 'Pass' : 'Fail'"></span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select x-model="band.color" :name="`bands[${i}][color]`"
                                                class="block w-32 rounded-lg border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <button type="button" @click="removeBand(i)" x-show="bands.length > 2"
                                                class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove band">
                                            <x-ui.icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            {{-- Try it --}}
            <x-ui.card>
                <h3 class="font-display font-semibold text-ink">Try it</h3>
                <p class="mt-1 text-xs text-ink/60">Enter a score to see which band it maps to, using the bands above.</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <input type="number" min="0" max="100" x-model.number="tryScore"
                           class="block w-28 rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <span class="text-sm text-ink/50">%</span>
                    <span class="font-display text-lg font-semibold text-crimson" x-text="previewLine(tryScore)"></span>
                    <template x-if="bandFor(tryScore)">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                              :class="bandFor(tryScore).is_pass ? 'bg-success/10 text-success' : 'bg-crimson/10 text-crimson'"
                              x-text="bandFor(tryScore).is_pass ? 'Pass' : 'Fail'"></span>
                    </template>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2 border-t border-line pt-5">
                <x-ui.button type="button" variant="ghost" :href="route('admin.grade-scales.index')">Cancel</x-ui.button>
                <x-ui.button type="submit">{{ $isNew ? 'Create scale' : 'Save scale' }}</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
