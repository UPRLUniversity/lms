@php
    use App\Enums\CouponScope;
    use App\Enums\CouponType;

    /** @var \App\Models\Coupon|null $coupon */
    $coupon ??= null;
    $editing = $coupon !== null;

    $currentType = old('type', $coupon?->type->value ?? CouponType::Percentage->value);
    $currentScope = old('scope', $coupon?->scope->value ?? CouponScope::Course->value);
    $fmt = fn ($date) => $date?->format('Y-m-d\TH:i');
@endphp

<div class="space-y-6"
     x-data="{
        type: @js($currentType),
        scope: @js($currentScope),
        get usesValue() { return this.type !== 'full'; },
     }">

    <div class="grid gap-5 sm:grid-cols-2">
        @if ($editing)
            {{-- The code is immutable once issued: somebody may already be holding it. --}}
            <div>
                <span class="block text-sm font-medium text-ink">Code</span>
                <p class="mt-1.5 rounded-xl border border-line bg-ink/5 px-4 py-2.5 font-mono font-bold tracking-wide text-ink">
                    {{ $coupon->code }}
                </p>
                <p class="mt-1 text-xs text-ink/55">A code cannot be renamed — students may already be holding it.</p>
            </div>
        @else
            <x-ui.field name="code" label="Code" required :value="old('code')"
                        placeholder="e.g. WELCOME20" class="uppercase"
                        hint="What the student types at checkout. Letters, numbers, dots, dashes." />
        @endif

        <x-ui.field name="name" label="Internal name" hint="Optional — only staff see this."
                    :value="old('name', $coupon?->name)" placeholder="e.g. Open day promotion" />
    </div>

    {{-- Discount --}}
    <fieldset class="rounded-xl border border-line bg-surface/40 p-4">
        <legend class="px-1.5 text-sm font-medium text-ink">The discount</legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field name="type" label="Type" required>
                <select id="type" name="type" x-model="type"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($currentType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <div x-show="usesValue" x-cloak>
                <x-ui.field name="value" label="Amount" type="number" min="0" step="0.01" inputmode="decimal"
                            :value="old('value', $coupon && $coupon->type->usesValue() ? $coupon->value : null)" />
                {{-- The hint changes with the type, so it lives outside the component
                     rather than fighting its string prop. --}}
                <p class="mt-1 text-xs text-ink/70"
                   x-text="type === 'percentage' ? 'A percentage — enter 20 for 20% off.' : 'A fixed amount off, in ' + @js(\App\Support\Money::currency()) + '.'"></p>
            </div>
        </div>

        <p class="mt-3 text-xs text-ink/60">
            Discounts apply to course prices only. One-off programme registration and administration
            fees are never discounted.
        </p>
    </fieldset>

    {{-- Scope --}}
    <fieldset class="rounded-xl border border-line bg-surface/40 p-4">
        <legend class="px-1.5 text-sm font-medium text-ink">What it applies to</legend>

        @if ($editing)
            <p class="text-sm text-ink/75">
                {{ $coupon->scope->label() }}@if ($coupon->course) — {{ $coupon->course->code }}@elseif ($coupon->programme) — {{ $coupon->programme->name }}@endif
            </p>
            <p class="mt-1 text-xs text-ink/55">Scope cannot be changed after a code is created — it would rewrite what past uses meant.</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field name="scope" label="Applies to" required>
                    <select id="scope" name="scope" x-model="scope"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach ($scopes as $scopeOption)
                            <option value="{{ $scopeOption->value }}" @selected($currentScope === $scopeOption->value)>{{ $scopeOption->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <div x-show="scope === 'course'" x-cloak>
                    <x-ui.field name="course_id" label="Course" >
                        <select id="course_id" name="course_id"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option value="">Choose…</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                    {{ $course->code }} — {{ $course->title }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>

                @if ($isAdmin)
                    <div x-show="scope === 'programme'" x-cloak>
                        <x-ui.field name="programme_id" label="Programme">
                            <select id="programme_id" name="programme_id"
                                    class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <option value="">Choose…</option>
                                @foreach ($programmes as $programme)
                                    <option value="{{ $programme->id }}" @selected(old('programme_id') == $programme->id)>
                                        {{ $programme->code }} — {{ $programme->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                @endif
            </div>

            @unless ($isAdmin)
                <p class="mt-3 text-xs text-ink/60">You can issue codes for courses you teach. Ask an administrator for a catalogue-wide code.</p>
            @endunless
        @endif
    </fieldset>

    {{-- Limits & window --}}
    <fieldset class="rounded-xl border border-line bg-surface/40 p-4">
        <legend class="px-1.5 text-sm font-medium text-ink">Limits</legend>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.field name="max_redemptions" label="Total uses" type="number" min="1" step="1"
                        hint="Leave blank for unlimited."
                        :value="old('max_redemptions', $coupon?->max_redemptions)" />
            <x-ui.field name="per_user_limit" label="Uses per student" type="number" min="1" step="1"
                        :value="old('per_user_limit', $coupon?->per_user_limit ?? 1)" />
            <x-ui.field name="starts_at" label="Starts" type="datetime-local" hint="Optional."
                        :value="old('starts_at', $fmt($coupon?->starts_at))" />
            <x-ui.field name="expires_at" label="Ends" type="datetime-local" hint="Optional."
                        :value="old('expires_at', $fmt($coupon?->expires_at))" />
        </div>
    </fieldset>

    <label class="flex items-start gap-3 rounded-xl border border-line p-4 hover:bg-surface/60 focus-within:ring-2 focus-within:ring-crimson">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="mt-0.5 rounded border-line text-crimson focus:ring-crimson"
               @checked(old('is_active', $coupon?->is_active ?? true))>
        <span>
            <span class="block text-sm font-medium text-ink">Active</span>
            <span class="block text-xs text-ink/60">Switch off to stop the code working without deleting it.</span>
        </span>
    </label>
</div>
