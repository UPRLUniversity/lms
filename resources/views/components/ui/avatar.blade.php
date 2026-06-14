@props([
    'user' => null,
    'name' => null,
    'src' => null,
    'size' => 'md',
])

@php
    // Resolve from a User model when given one, else from explicit name/src props.
    $displayName = $user?->name ?? $name ?? '';
    $url = $src ?? ($user?->avatarUrl());
    $initials = $user?->initials() ?? (\Illuminate\Support\Str::of($displayName)
        ->explode(' ')->filter()
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
        ->take(2)->implode('') ?: 'U');

    $sizes = [
        'xs' => 'h-7 w-7 text-xs',
        'sm' => 'h-9 w-9 text-sm',
        'md' => 'h-11 w-11 text-base',
        'lg' => 'h-16 w-16 text-xl',
        'xl' => 'h-24 w-24 text-3xl',
    ];
    $dimension = $sizes[$size] ?? $sizes['md'];
@endphp

@if ($url)
    <img
        src="{{ $url }}"
        alt="{{ $displayName }}"
        {{ $attributes->merge(['class' => "inline-block shrink-0 rounded-full object-cover {$dimension}"]) }}
    >
@else
    <span
        aria-hidden="true"
        {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full bg-crimson font-semibold text-white {$dimension}"]) }}
    >{{ $initials }}</span>
    <span class="sr-only">{{ $displayName }}</span>
@endif
