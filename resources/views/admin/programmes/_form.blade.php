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
        <p class="text-xs text-ink/60">Shown on the programme card and its landing page. 1600×600 works best. JPG, PNG or WebP, up to {{ $maxMb }}MB.</p>
        <div class="mt-2 flex flex-wrap items-center gap-4">
            <div class="relative aspect-[8/3] w-56 overflow-hidden rounded-xl border border-line bg-gradient-to-br from-crimson to-crimson-dark">
                <template x-if="coverPreview">
                    <img :src="coverPreview" alt="" class="h-full w-full object-cover">
                </template>
                @if ($programme?->coverUrl())
                    <img x-show="!coverPreview" src="{{ $programme->coverUrl() }}" alt="Current cover" class="h-full w-full object-cover">
                @else
                    <div x-show="!coverPreview" class="absolute inset-0 flex items-center justify-center">
                        <span class="font-display text-lg font-bold text-white/80">{{ $programme->code ?? 'Cover' }}</span>
                    </div>
                @endif
            </div>
            <div>
                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-card px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface focus-within:ring-2 focus-within:ring-crimson">
                    <x-ui.icon name="camera" class="h-5 w-5" /> Choose image
                    <input type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="sr-only"
                           @change="previewCover($event)">
                </label>
                <p class="mt-1 text-xs text-ink/50" x-text="coverName"></p>
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
        <p class="mb-3 text-xs text-ink/60">
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
    <fieldset class="rounded-xl border border-line bg-surface/40 p-4"
              x-data="{ rule: '{{ old('progression_rule', $programme?->progression_rule?->value ?? 'open') }}' }">
        <legend class="px-1.5 text-sm font-medium text-ink">Progression between parts</legend>

        @php $selectedRule = old('progression_rule', $programme?->progression_rule?->value ?? 'open'); @endphp

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
                        <span class="block text-xs leading-relaxed text-ink/60">{{ $rule->help() }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        {{-- The blast radius is invisible from this form, so point at the command that
             shows it BEFORE the switch rather than after somebody complains. --}}
        <p x-show="rule === 'sequential'" x-cloak
           class="mt-3 rounded-xl bg-gold/10 px-3 py-2.5 text-xs leading-relaxed text-ink/75">
            <span class="font-medium text-ink">Before you switch this on:</span>
            run <code class="rounded bg-ink/5 px-1 py-0.5 font-mono">php artisan progression:audit</code> to see which
            students are already enrolled in courses this rule would have blocked. Nobody loses access they already
            have — the rule applies to new enrolments only — but it is worth knowing who is affected.
        </p>

        <x-input-error :messages="$errors->get('progression_rule')" class="mt-2" />
    </fieldset>

    {{-- Visibility --}}
    <label class="flex items-start gap-3 rounded-xl border border-line p-4 hover:bg-surface/60 focus-within:ring-2 focus-within:ring-crimson">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="mt-0.5 rounded border-line text-crimson focus:ring-crimson"
               @checked(old('is_active', $programme?->is_active ?? true))>
        <span>
            <span class="block text-sm font-medium text-ink">Active</span>
            <span class="block text-xs text-ink/60">Inactive programmes stay intact but are hidden from the public catalogue filters.</span>
        </span>
    </label>
</div>
