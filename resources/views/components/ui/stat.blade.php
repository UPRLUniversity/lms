@props([
    'label',
    'value',
    'icon' => null,
    'tone' => 'crimson',
])

@php
    $tones = [
        'crimson' => 'bg-crimson/10 text-crimson',
        'success' => 'bg-success/10 text-success',
        'gold' => 'bg-gold/15 text-gold',
        'neutral' => 'bg-ink/5 text-ink',
    ];
    $iconClasses = $tones[$tone] ?? $tones['crimson'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl bg-card border border-line shadow-sm p-5']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium text-ink/70">{{ $label }}</p>
        @if ($icon)
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg {{ $iconClasses }}">
                <x-ui.icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
    <p class="mt-2 font-display text-3xl font-semibold text-ink">{{ $value }}</p>
    @isset($caption)
        <p class="mt-1 text-xs text-ink/70">{{ $caption }}</p>
    @endisset
</div>
