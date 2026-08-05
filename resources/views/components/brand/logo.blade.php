@props([
    'variant' => 'color',
    // Pass alt="" when the logo sits inside an already-labelled link (decorative).
    'alt' => null,
])

@php
    // Resolved through BrandAssets: an upload from Settings → Branding wins, the
    // file shipped in public/images/brand/ is the fallback, and null means neither
    // exists — in which case the inline monogram below keeps the app presentable.
    $src = brand_assets()->url($variant);

    $reversed = $variant === 'white';   // reversed knockout lockup sits on dark surfaces
    $markOnly = $variant === 'mark';    // symbol only, no wordmark
    $altText = $alt ?? config('brand.university');
@endphp

@if ($src)
    <img src="{{ $src }}"
         alt="{{ $altText }}"
         {{ $attributes->merge(['class' => 'h-9 w-auto']) }}>
@else
    {{-- Inline fallback monogram so the app is presentable before real artwork lands. --}}
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <span aria-hidden="true" @class([
            'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold tracking-tight',
            'bg-white text-crimson' => $reversed,
            'bg-crimson text-white' => ! $reversed,
        ])>
            {{ config('brand.short') }}
        </span>
        @unless ($markOnly)
            <span @class([
                'font-display text-lg font-semibold leading-none',
                'text-white' => $reversed,
                'text-ink' => ! $reversed,
            ])>
                {{ config('brand.name') }}
            </span>
        @endunless
    </span>
@endif
