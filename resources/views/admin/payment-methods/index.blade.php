@php
    use App\Enums\PaymentEnvironment;
@endphp

<x-app-layout title="Payment methods">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Payment methods</h2>
            <p class="mt-1 max-w-2xl text-ink/70">
                How students pay for courses. Switch one on, paste its keys, and it appears at checkout.
            </p>
        </div>

        <div class="space-y-4">
            @foreach ($cards as $card)
                @php
                    /** @var \App\Models\PaymentMethod|null $method */
                    $method = $card['method'];
                    $installed = $method !== null;
                    $ready = $installed && $method->isConfigured();
                @endphp

                <x-ui.card :padding="false"
                           x-data="{ open: {{ $installed && ! $ready ? 'true' : 'false' }} }"
                           @class(['overflow-hidden', 'opacity-70' => ! $installed])>

                    {{-- Header row --}}
                    <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                            <span @class([
                                'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                'bg-crimson/10 text-crimson' => $installed,
                                'bg-ink/5 text-ink/40' => ! $installed,
                            ])>
                                <x-ui.icon name="{{ $card['key'] === 'bank_transfer' ? 'banknote' : 'credit-card' }}" class="h-5 w-5" />
                            </span>

                            <span class="font-display text-lg font-semibold text-ink">{{ $card['label'] }}</span>

                            @if ($card['supports_subscriptions'])
                                <x-ui.badge variant="success">Supports subscriptions</x-ui.badge>
                            @endif

                            @if ($installed && $method->is_enabled)
                                <x-ui.badge :variant="$method->environment->badge()">{{ $method->environment->label() }}</x-ui.badge>
                            @endif

                            @if ($installed && ! $ready)
                                <x-ui.badge variant="gold">Needs configuration</x-ui.badge>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if (! $installed)
                                @if ($canManage)
                                    <form method="POST" action="{{ route('admin.payment-methods.store') }}">
                                        @csrf
                                        <input type="hidden" name="key" value="{{ $card['key'] }}">
                                        <x-ui.button size="sm" variant="secondary" type="submit">Set up</x-ui.button>
                                    </form>
                                @else
                                    <span class="text-xs text-ink/45">Not installed</span>
                                @endif
                            @else
                                @if ($canManage)
                                    {{-- Toggle. A real button, not a checkbox in a form that
                                         needs a separate save — a switch that does not switch
                                         anything is a lie. --}}
                                    <form method="POST" action="{{ route('admin.payment-methods.toggle', $method) }}">
                                        @csrf
                                        <input type="hidden" name="enable" value="{{ $method->is_enabled ? 0 : 1 }}">
                                        <button type="submit" role="switch"
                                                aria-checked="{{ $method->is_enabled ? 'true' : 'false' }}"
                                                aria-label="{{ $method->is_enabled ? 'Switch off' : 'Switch on' }} {{ $card['label'] }}"
                                                @class([
                                                    'relative inline-flex h-6 w-11 shrink-0 rounded-full transition focus-ring',
                                                    'bg-crimson' => $method->is_enabled,
                                                    'bg-ink/20' => ! $method->is_enabled,
                                                ])>
                                            <span @class([
                                                'inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition',
                                                'translate-x-[1.375rem]' => $method->is_enabled,
                                                'translate-x-0.5' => ! $method->is_enabled,
                                            ])></span>
                                        </button>
                                    </form>
                                @endif

                                <button type="button" @click="open = !open"
                                        class="rounded-lg p-2 text-ink/50 hover:bg-ink/5 hover:text-ink focus-ring"
                                        :aria-expanded="open.toString()"
                                        aria-label="Configure {{ $card['label'] }}">
                                    <x-ui.icon name="cog" class="h-5 w-5" />
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Configuration --}}
                    @if ($installed)
                        <div x-show="open" x-collapse x-cloak class="border-t border-line bg-surface/50">
                            <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="space-y-4 p-5">
                                @csrf @method('PUT')
                                <fieldset @disabled(! $canManage) class="space-y-4">

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <x-ui.field name="label" label="Display name" :value="old('label', $method->label)" />

                                        <x-ui.field name="environment" label="Environment">
                                            <select id="environment" name="environment"
                                                    class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                                @foreach ($environments as $environment)
                                                    <option value="{{ $environment->value }}"
                                                            @selected(old('environment', $method->environment->value) === $environment->value)>
                                                        {{ $environment->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </x-ui.field>
                                    </div>

                                    {{-- Driver-declared credentials --}}
                                    @foreach (array_keys((array) ($method->driverConfig()['config'] ?? [])) as $key)
                                        @php
                                            $isSecret = str_contains($key, 'secret');
                                            $stored = (string) $method->setting($key, '');
                                        @endphp
                                        <div x-data="{ show: false }">
                                            <label for="config-{{ $key }}" class="block text-sm font-medium text-ink">
                                                {{ ucfirst(str_replace('_', ' ', $key)) }}
                                            </label>
                                            <div class="relative mt-1.5">
                                                {{-- A stored secret is never rendered back; the placeholder
                                                     says it exists and a blank submit leaves it untouched. --}}
                                                <input :type="show ? 'text' : '{{ $isSecret ? 'password' : 'text' }}'"
                                                       id="config-{{ $key }}"
                                                       name="config[{{ $key }}]"
                                                       value="{{ $isSecret ? '' : $stored }}"
                                                       placeholder="{{ $isSecret && $stored !== '' ? '•••••••••••••••••••• (saved)' : 'Paste your '.str_replace('_', ' ', $key) }}"
                                                       autocomplete="off" spellcheck="false"
                                                       class="block w-full rounded-xl border-line bg-card pr-11 font-mono text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                                @if ($isSecret)
                                                    <button type="button" @click="show = !show"
                                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-ink/40 hover:text-ink focus-ring rounded-r-xl"
                                                            :aria-label="show ? 'Hide' : 'Show'">
                                                        <x-ui.icon name="eye" class="h-4 w-4" x-show="!show" />
                                                        <x-ui.icon name="eye-off" class="h-4 w-4" x-show="show" x-cloak />
                                                    </button>
                                                @endif
                                            </div>
                                            @if ($isSecret && $stored !== '')
                                                <p class="mt-1 text-xs text-ink/50">Leave blank to keep the saved key.</p>
                                            @endif
                                        </div>
                                    @endforeach

                                    {{-- Webhook URL --}}
                                    @if ($card['key'] !== 'bank_transfer' && $card['key'] !== 'sandbox')
                                        <div x-data="{ copied: false }">
                                            <span class="block text-sm font-medium text-ink">Webhook URL</span>
                                            <p class="text-xs text-ink/60">Paste this into your {{ $card['label'] }} dashboard so payments confirm automatically.</p>
                                            <div class="mt-1.5 flex gap-2">
                                                <input type="text" readonly value="{{ $method->webhookUrl() }}"
                                                       x-ref="hook"
                                                       class="block w-full truncate rounded-xl border-line bg-ink/5 font-mono text-xs text-ink/70 shadow-sm focus:border-crimson focus:ring-crimson">
                                                <x-ui.button type="button" variant="secondary" size="sm"
                                                             @click="navigator.clipboard.writeText($refs.hook.value); copied = true; setTimeout(() => copied = false, 2000)">
                                                    <span x-show="!copied"><x-ui.icon name="clipboard" class="h-4 w-4" /> Copy</span>
                                                    <span x-show="copied" x-cloak class="text-success"><x-ui.icon name="check" class="h-4 w-4" /> Copied</span>
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Offline instructions --}}
                                    @if ($card['key'] === 'bank_transfer')
                                        <x-ui.rich-editor
                                            name="instructions"
                                            label="Payment instructions"
                                            profile="basic"
                                            hint="Your account name, number and bank. Shown to the student on their order, and required before this method can be switched on."
                                            :value="old('instructions', $method->instructions)" />
                                    @endif

                                    @if ($canManage)
                                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                                            <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}"
                                                  onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Remove {{ $card['label'] }}?', text: 'Its saved keys will be deleted.', confirmText: 'Remove', danger: true }).then(ok => ok &amp;&amp; this.submit());">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded text-xs font-medium text-ink/50 hover:text-crimson focus-ring">Remove</button>
                                            </form>
                                            <x-ui.button type="submit">Save changes</x-ui.button>
                                        </div>
                                    @endif
                                </fieldset>
                            </form>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>

        <div class="rounded-xl border border-line bg-surface/60 p-4 text-sm text-ink/70">
            <p class="font-medium text-ink">A note on keys</p>
            <p class="mt-1">
                Secret keys are encrypted before they are stored and are never shown again once saved —
                not on this page, not in a database export. Rotate one by pasting a new value over it.
            </p>
        </div>
    </div>
</x-app-layout>
