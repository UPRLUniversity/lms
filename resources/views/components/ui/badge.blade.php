@props([
    'variant' => 'neutral',
    'solid' => false,
])

@php
    // Default (tinted) badges — for light surfaces like table cells and lists.
    //
    // Gold uses text-gold-ink on a LIGHTER tint, not the base gold. The brand system says
    // it outright — base gold is not legible as text on a light surface — and a Lighthouse
    // pass measured the old `bg-gold/10 text-gold-ink` pairing at 2.08:1 against a 4.5:1
    // requirement. `bg-gold/10 text-gold-ink` measures 4.67:1 and keeps the badge gold.
    $tinted = [
        'neutral' => 'bg-ink/5 text-ink',
        'crimson' => 'bg-crimson/10 text-crimson',
        'success' => 'bg-success/10 text-success',
        'gold' => 'bg-gold/10 text-gold-ink',
        'ink' => 'bg-ink/10 text-ink',
    ];

    // Solid badges — an opaque white pill with a strong, AA-contrast label. Used when
    // a badge sits ON a cover image/gradient, where tinted backgrounds wash out.
    $solidVariants = [
        'neutral' => 'bg-white text-ink ring-1 ring-black/5 shadow-sm',
        'crimson' => 'bg-white text-crimson ring-1 ring-black/5 shadow-sm',
        'success' => 'bg-white text-success ring-1 ring-black/5 shadow-sm',
        'gold' => 'bg-white text-gold-ink ring-1 ring-black/5 shadow-sm',
        'ink' => 'bg-white text-ink ring-1 ring-black/5 shadow-sm',
    ];

    $palette = $solid ? $solidVariants : $tinted;

    $classes = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium '
        .($palette[$variant] ?? $palette['neutral']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
