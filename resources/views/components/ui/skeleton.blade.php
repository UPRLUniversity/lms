{{--
    A single shimmer placeholder block. Decorative (aria-hidden) — the surrounding
    region carries aria-busy for assistive tech. Size it with utility classes:
        <x-ui.skeleton class="h-4 w-32" />
--}}
<div {{ $attributes->merge(['class' => 'skeleton h-4 w-full']) }} aria-hidden="true"></div>
