@php
    // Hidden entirely unless Settings → General turns it on. A switcher offering one
    // language is noise, so it stays off until a second language actually exists.
    $enabled = (bool) setting('general.locale_switcher_enabled', false);
    $locales = config('settings.locales', []);
    $current = app()->getLocale();
@endphp

@if ($enabled && count($locales) > 1)
    <div {{ $attributes->merge(['class' => 'relative']) }}
         x-data="{ open: false }"
         @click.outside="open = false"
         @keydown.escape="open = false">
        <button type="button"
                @click="open = ! open"
                :aria-expanded="open.toString()"
                aria-haspopup="true"
                class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-ink/70 hover:bg-ink/5 hover:text-ink focus-ring">
            <span class="sr-only">{{ __('nav.choose_language') }}</span>
            <x-ui.icon name="link" class="h-4 w-4" aria-hidden="true" />
            <span aria-hidden="true">{{ $locales[$current] ?? strtoupper($current) }}</span>
        </button>

        <div x-show="open"
             x-transition
             style="display:none"
             class="absolute right-0 z-50 mt-1 min-w-40 overflow-hidden rounded-xl border border-line bg-card py-1 shadow-lg">
            <ul role="list">
                @foreach ($locales as $code => $label)
                    <li>
                        <form method="POST" action="{{ route('locale.update', $code) }}">
                            @csrf
                            <button type="submit"
                                    @class([
                                        'block w-full px-3 py-2 text-left text-sm hover:bg-surface focus-ring',
                                        'font-semibold text-crimson' => $code === $current,
                                        'text-ink/80' => $code !== $current,
                                    ])
                                    @if ($code === $current) aria-current="true" @endif>
                                {{ $label }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
