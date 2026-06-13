@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-medium rounded-xl border transition-colors focus-ring disabled:opacity-50 disabled:pointer-events-none whitespace-nowrap';

    $variants = [
        'primary' => 'bg-crimson border-transparent text-white hover:bg-crimson-dark',
        'secondary' => 'bg-card border-line text-ink hover:bg-surface',
        'ghost' => 'bg-transparent border-transparent text-ink hover:bg-ink/5',
        'danger' => 'bg-transparent border-crimson text-crimson hover:bg-crimson hover:text-white',
    ];

    $sizes = [
        'sm' => 'text-sm px-3 py-1.5',
        'md' => 'text-sm px-4 py-2.5',
        'lg' => 'text-base px-6 py-3',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']));
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    {{ $slot }}
</{{ $tag }}>
