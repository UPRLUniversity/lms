@php
    use App\Enums\MediaPurpose;

    /** @var \App\Models\Programme|null $programme */
    $programme ??= null;
    $maxMb = round(MediaPurpose::ProgrammeCovers->maxKb() / 1024);
@endphp

<div class="space-y-6"
     x-data="{
        coverPreview: null,
        coverName: '',
        previewCover(event) {
            const file = event.target.files[0];
            if (!file) { this.coverPreview = null; this.coverName = ''; return; }
            this.coverName = file.name;
            this.coverPreview = URL.createObjectURL(file);
        },
     }">

    {{-- Cover --}}
    <div>
        <span class="block text-sm font-medium text-ink">Cover image</span>
        <p class="text-xs text-ink/65">Shown on the programme card and its landing page. 1600×600 works best. JPG, PNG or WebP, up to {{ $maxMb }}MB.</p>
        <div class="mt-2 flex flex-wrap items-center gap-4">
            <div class="relative aspect-[8/3] w-56 overflow-hidden rounded-xl border border-line bg-gradient-to-br from-crimson to-crimson-dark">
                <template x-if="coverPreview">
                    <img :src="coverPreview" alt="" class="h-full w-full object-cover">
                </template>
                @if ($programme?->coverUrl())
                    <img x-show="!coverPreview" src="{{ $programme->coverUrl() }}" alt="Current cover" class="h-full w-full object-cover">
                @else
                    <div x-show="!coverPreview" class="absolute inset-0 flex items-center justify-center">
                        <span class="font-display text-lg font-bold text-white/85">{{ $programme->code ?? 'Cover' }}</span>
                    </div>
                @endif
            </div>
            <div>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-card px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface focus-within:ring-2 focus-within:ring-crimson">
                    <x-ui.icon name="camera" class="h-5 w-5" /> Choose image
                    <input type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="sr-only"
                           @change="previewCover($event)">
                </label>
                <p class="mt-1 text-xs text-ink/65" x-text="coverName"></p>
            </div>
        </div>
        <x-input-error :messages="$errors->get('cover')" class="mt-2" />
    </div>

    {{-- Identity --}}
    <div class="grid gap-5 sm:grid-cols-3">
        <x-ui.field class="sm:col-span-2" name="name" label="Programme name" required
                    :value="old('name', $programme?->name)"
                    placeholder="e.g. Professional Certificate in Public Relations" />
        <x-ui.field name="code" label="Code" required
                    :value="old('code', $programme?->code)"
                    hint="Short, e.g. CPR" placeholder="CPR" />
    </div>

    <x-ui.field name="tagline" label="Tagline" hint="One line, shown on the programme card."
                :value="old('tagline', $programme?->tagline)"
                placeholder="e.g. The entry qualification for practising public relations in Nigeria." />

    <x-ui.rich-editor
        name="description"
        label="Description"
        profile="basic"
        hint="Optional. Appears on the programme's public page."
        :value="old('description', $programme?->description)" />

    {{-- Fees --}}
    <fieldset class="rounded-xl border border-line bg-surface/40 p-4">
        <legend class="px-1.5 text-sm font-medium text-ink">Fee schedule</legend>
        <p class="mb-3 text-xs text-ink/65">
            Registration and administration are charged <strong class="font-medium text-ink/75">once</strong>, on a student's
            first purchase in this programme. Per paper is the default price of each course placed here — an individual
            course can override it.
        </p>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.field name="registration_fee" label="Registration" type="number"
                        :value="old('registration_fee', $programme?->registration_fee ?? 0)"
                        min="0" step="0.01" inputmode="decimal" />
            <x-ui.field name="administration_fee" label="Administration" type="number"
                        :value="old('administration_fee', $programme?->administration_fee ?? 0)"
                        min="0" step="0.01" inputmode="decimal" />
            <x-ui.field name="per_paper_fee" label="Per paper" type="number"
                        :value="old('per_paper_fee', $programme?->per_paper_fee ?? 0)"
                        min="0" step="0.01" inputmode="decimal" />
        </div>
    </fieldset>

    {{-- Progression --}}
    @php $selectedRule = old('progression_rule', $programme?->progression_rule?->value ?? 'open'); @endphp

    <fieldset class="rounded-xl border border-line bg-surface/40 p-4"
              x-data="progressionImpact({
                  rule: @js($selectedRule),
                  impactUrl: @js($programme ? route('admin.programmes.progression-impact', $programme) : null),
              })">
        <legend class="px-1.5 text-sm font-medium text-ink">Progression between parts</legend>

        <div class="mt-1 space-y-2">
            @foreach (\App\Enums\ProgressionRule::cases() as $rule)
                <label class="flex items-start gap-3 rounded-xl border border-line bg-card p-3 hover:bg-surface/60 focus-within:ring-2 focus-within:ring-crimson">
                    {{-- Checked server-side as well as through x-model, so the form still
                         posts a value with JavaScript off, like the rest of this page. --}}
                    <input type="radio" name="progression_rule" value="{{ $rule->value }}" x-model="rule"
                           @checked($selectedRule === $rule->value)
                           class="mt-0.5 border-line text-crimson focus:ring-crimson">
                    <span>
                        <span class="block text-sm font-medium text-ink">{{ $rule->label() }}</span>
                        <span class="block text-xs leading-relaxed text-ink/70">{{ $rule->help() }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        {{-- The blast radius is invisible from a form, so the form answers it. Selecting
             the rule audits the programme's live enrolments and says who it would have
             stopped, here, before the admin saves anything. --}}
        <div x-show="sequential" x-cloak x-collapse class="mt-3">
            @if ($programme)
                <div x-show="state === 'loading'" aria-busy="true" class="rounded-xl border border-line bg-card p-3">
                    <p class="text-xs font-medium text-ink/65">Checking who this would affect…</p>
                    <div class="mt-2 space-y-2">
                        <x-ui.skeleton class="h-3 w-3/4" />
                        <x-ui.skeleton class="h-3 w-1/2" />
                    </div>
                </div>

                {{-- Nobody blocked. Worth saying out loud: silence would read as "not checked". --}}
                <template x-if="state === 'ready' && impact.blocked === 0">
                    <div class="flex items-start gap-2.5 rounded-xl border border-success/25 bg-success/5 p-3">
                        <x-ui.icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-success" />
                        <p class="text-xs leading-relaxed text-ink/75">
                            <span class="font-medium text-success">Nobody is affected.</span>
                            <span x-text="clearDetail"></span>
                        </p>
                    </div>
                </template>

                <template x-if="state === 'ready' && impact.blocked > 0">
                    <div class="rounded-xl bg-gold/10 p-3">
                        <div class="flex items-start gap-2.5">
                            <x-ui.icon name="lock" class="mt-0.5 h-4 w-4 shrink-0 text-gold-ink" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs leading-relaxed text-ink/75">
                                    <span class="font-medium text-gold-ink" x-text="headline"></span>
                                    They keep the access they already have. The rule applies to new enrolments only.
                                </p>

                                <button type="button" @click="expanded = ! expanded"
                                        :aria-expanded="expanded"
                                        class="mt-1.5 rounded font-medium text-xs text-crimson underline underline-offset-2 hover:text-crimson-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson">
                                    <span x-text="expanded ? 'Hide the list' : 'See who is affected'"></span>
                                </button>

                                <div x-show="expanded" x-collapse class="mt-2">
                                    {{-- Capped height: a hundred rows must not push the Save
                                         button off the end of the form. --}}
                                    <div class="max-h-64 overflow-auto rounded-lg border border-line bg-card">
                                        <table class="w-full text-left text-xs">
                                            <thead class="sticky top-0 border-b border-line bg-card text-ink/65">
                                                <tr>
                                                    <th scope="col" class="px-2.5 py-1.5 font-medium">Student</th>
                                                    <th scope="col" class="px-2.5 py-1.5 font-medium">Course</th>
                                                    <th scope="col" class="px-2.5 py-1.5 font-medium">Blocked by</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-line">
                                                <template x-for="(row, i) in impact.rows" :key="i">
                                                    <tr>
                                                        <td class="px-2.5 py-1.5 text-ink" x-text="row.student"></td>
                                                        <td class="px-2.5 py-1.5 text-ink/75" x-text="row.course"></td>
                                                        <td class="px-2.5 py-1.5 text-ink/65" x-text="row.blockedBy ?? '—'"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p x-show="impact.truncated > 0" class="mt-1.5 text-xs text-ink/65">
                                        and <span x-text="impact.truncated"></span> more, not listed here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="state === 'failed'" x-cloak
                     class="flex items-start justify-between gap-3 rounded-xl border border-line bg-card p-3">
                    <p class="text-xs leading-relaxed text-ink/75">
                        We could not check who this would affect just now. Saving is still safe: nobody
                        loses access they already have.
                    </p>
                    <button type="button" @click="retry()"
                            class="shrink-0 rounded text-xs font-medium text-crimson underline underline-offset-2 hover:text-crimson-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson">
                        Try again
                    </button>
                </div>
            @else
                <p class="rounded-xl bg-gold/10 px-3 py-2.5 text-xs leading-relaxed text-ink/75">
                    <span class="font-medium text-gold-ink">Nobody is affected yet.</span>
                    A new programme has no students, so this rule will only ever apply to enrolments made
                    from now on.
                </p>
            @endif
        </div>

        <x-input-error :messages="$errors->get('progression_rule')" class="mt-2" />
    </fieldset>

    {{-- Visibility --}}
    <label class="flex items-start gap-3 rounded-xl border border-line p-4 hover:bg-surface/60 focus-within:ring-2 focus-within:ring-crimson">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="mt-0.5 rounded border-line text-crimson focus:ring-crimson"
               @checked(old('is_active', $programme?->is_active ?? true))>
        <span>
            <span class="block text-sm font-medium text-ink">Active</span>
            <span class="block text-xs text-ink/65">Inactive programmes stay intact but are hidden from the public catalogue filters.</span>
        </span>
    </label>
</div>
