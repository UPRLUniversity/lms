@php
    use App\Http\Requests\Admin\SettingsRequest;

    /** Encode a dotted setting key for a form field name (see SettingsRequest). */
    $field = fn (string $key) => SettingsRequest::encode($key);
@endphp

<x-app-layout :title="'Settings · '.$active->label()">
    <x-slot name="breadcrumbs">
        <x-ui.breadcrumbs :items="[
            ['label' => 'Administration'],
            ['label' => 'Settings', 'href' => route('admin.settings.index')],
            ['label' => $active->label()],
        ]" />
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">System settings</h2>
            <p class="mt-1 max-w-2xl text-ink/70">
                Institution-wide configuration. Changes take effect immediately, and every save is
                recorded in the <a href="{{ route('admin.audit.index') }}" class="font-medium text-crimson underline-offset-2 hover:underline focus-ring rounded">audit trail</a>.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
            {{-- Tabs. A real nav landmark, and each tab is a linkable URL. --}}
            <nav aria-label="Settings sections" class="lg:sticky lg:top-20 lg:self-start">
                <ul class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0">
                    @foreach ($groups as $group)
                        <li class="shrink-0">
                            <a href="{{ route('admin.settings.index', $group->value) }}"
                               @class([
                                   'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition-colors focus-ring',
                                   'bg-crimson text-white' => $group === $active,
                                   'text-ink/70 hover:bg-ink/5 hover:text-ink' => $group !== $active,
                               ])
                               @if ($group === $active) aria-current="page" @endif>
                                <x-ui.icon :name="$group->icon()" class="h-5 w-5 shrink-0" />
                                {{ $group->label() }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <form method="POST"
                  action="{{ route('admin.settings.update') }}"
                  enctype="multipart/form-data"
                  class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="{{ $active->value }}">

                <x-ui.card>
                    <x-slot name="header">
                        <h3 class="font-display text-lg font-semibold text-ink">{{ $active->label() }}</h3>
                        <p class="mt-1 text-sm text-ink/70">{{ $active->description() }}</p>
                    </x-slot>

                    <div class="space-y-6">
                        @foreach ($definitions as $key => $definition)
                            @php
                                $name = $field($key);
                                $type = $definition['type'] ?? 'string';
                                $value = old('settings.'.$name, $values[$key] ?? null);
                                $error = $errors->first('settings.'.$name) ?: $errors->first('uploads.'.$name);
                                $errorId = $error ? $name.'-error' : null;
                                $hintId = ! empty($definition['help']) ? $name.'-hint' : null;
                                $describedBy = trim(implode(' ', array_filter([$hintId, $errorId]))) ?: null;
                            @endphp

                            @if ($type === 'bool')
                                {{-- Checkbox: label wraps the control, so the whole row is a hit target. --}}
                                <div class="flex items-start gap-3">
                                    {{-- Guarantees a value when unticked, so a toggle can be turned OFF. --}}
                                    <input type="hidden" name="settings[{{ $name }}]" value="0">
                                    <input type="checkbox"
                                           id="{{ $name }}"
                                           name="settings[{{ $name }}]"
                                           value="1"
                                           @checked($value)
                                           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                                           class="mt-0.5 h-5 w-5 shrink-0 rounded border-line text-crimson focus:ring-crimson focus-ring">
                                    <div class="min-w-0">
                                        <label for="{{ $name }}" class="block text-sm font-medium text-ink">
                                            {{ $definition['label'] }}
                                        </label>
                                        @if ($hintId)
                                            <p id="{{ $hintId }}" class="mt-0.5 text-xs text-ink/70">{{ $definition['help'] }}</p>
                                        @endif
                                        @if ($error)
                                            <p id="{{ $errorId }}" class="mt-1 text-sm text-crimson">{{ $error }}</p>
                                        @endif
                                    </div>
                                </div>

                            @elseif ($type === 'media')
                                @include('admin.settings.partials.media-field', [
                                    'key' => $key,
                                    'name' => $name,
                                    'definition' => $definition,
                                    'media' => $assets[$key] ?? null,
                                    'error' => $error,
                                    'errorId' => $errorId,
                                    'hintId' => $hintId,
                                ])

                            @else
                                <x-ui.field :name="'settings.'.$name"
                                            :id="$name"
                                            :label="$definition['label']"
                                            :hint="$definition['help'] ?? null">
                                    @if ($type === 'select')
                                        @php
                                            $opts = $definition['options'] ?? [];
                                            $opts = is_string($opts) ? ($options[$opts] ?? []) : $opts;
                                        @endphp
                                        <select id="{{ $name }}"
                                                name="settings[{{ $name }}]"
                                                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                                                @if ($error) aria-invalid="true" @endif
                                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                            @if (($definition['derived'] ?? false) && $opts === [])
                                                <option value="">No active scales — create one first</option>
                                            @endif
                                            @foreach ($opts as $optValue => $optLabel)
                                                <option value="{{ $optValue }}" @selected((string) $optValue === (string) $value)>
                                                    {{ $optLabel }}
                                                </option>
                                            @endforeach
                                        </select>

                                    @elseif ($type === 'timezone')
                                        <select id="{{ $name }}"
                                                name="settings[{{ $name }}]"
                                                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                            @foreach ($options['timezones'] as $zone => $zoneLabel)
                                                <option value="{{ $zone }}" @selected($zone === $value)>{{ $zoneLabel }}</option>
                                            @endforeach
                                        </select>

                                    @elseif ($type === 'text')
                                        <textarea id="{{ $name }}"
                                                  name="settings[{{ $name }}]"
                                                  rows="3"
                                                  @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                                                  @if ($error) aria-invalid="true" @endif
                                                  class="block w-full rounded-xl border-line bg-card text-ink shadow-sm placeholder:text-ink/65 focus:border-crimson focus:ring-crimson">{{ $value }}</textarea>

                                    @else
                                        <x-ui.input :type="$type === 'int' ? 'number' : ($type === 'email' ? 'email' : 'text')"
                                                    :id="$name"
                                                    :name="'settings['.$name.']'"
                                                    :value="$value"
                                                    :invalid="(bool) $error"
                                                    :aria-describedby="$describedBy" />
                                    @endif
                                </x-ui.field>
                            @endif
                        @endforeach

                        {{-- Cross-links to the screens that own the adjacent state, so this
                             page never becomes a second, competing owner of it. --}}
                        @if ($active === \App\Enums\SettingGroup::Grading)
                            <x-ui.card class="bg-surface/60">
                                <p class="text-sm text-ink/70">
                                    Bands, letters and grade points are edited on the
                                    <a href="{{ route('admin.grade-scales.index') }}" class="font-medium text-crimson underline-offset-2 hover:underline focus-ring rounded">grade scales screen</a>.
                                    This setting only chooses which of them is the default.
                                </p>
                            </x-ui.card>
                        @endif

                        @if ($active === \App\Enums\SettingGroup::Commerce)
                            <x-ui.card class="bg-surface/60">
                                <p class="text-sm text-ink/70">
                                    Gateway credentials are not kept here. They live encrypted on the
                                    <a href="{{ route('admin.payment-methods.index') }}" class="font-medium text-crimson underline-offset-2 hover:underline focus-ring rounded">payment methods screen</a>,
                                    where rotating one is recorded in the audit trail without its value.
                                </p>
                            </x-ui.card>
                        @endif
                    </div>

                    <x-slot name="footer">
                        <div class="flex items-center justify-end gap-3">
                            <x-ui.button variant="ghost" :href="route('admin.settings.index', $active->value)">Cancel</x-ui.button>
                            <x-ui.button type="submit">Save {{ strtolower($active->label()) }} settings</x-ui.button>
                        </div>
                    </x-slot>
                </x-ui.card>
            </form>
        </div>
    </div>
</x-app-layout>
