@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'bg-ink/5 text-ink',
        'crimson' => 'bg-crimson/10 text-crimson',
        'success' => 'bg-success/10 text-success',
        'gold' => 'bg-gold/15 text-gold',
    ];

    $classes = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium '
        .($variants[$variant] ?? $variants['neutral']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
