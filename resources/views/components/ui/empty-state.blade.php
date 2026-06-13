@props([
    'title',
    'description' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-xl border border-dashed border-line bg-card px-6 py-12 text-center']) }}>
    {{-- Faint sunburst motif behind the icon. --}}
    <x-brand.sunburst class="pointer-events-none absolute left-1/2 top-6 h-28 w-28 -translate-x-1/2 text-crimson/5" />

    <div class="relative mx-auto flex max-w-sm flex-col items-center">
        <span class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-crimson/10 text-crimson">
            <x-ui.icon :name="$icon" class="h-6 w-6" />
        </span>

        <h3 class="font-display text-lg font-semibold text-ink">{{ $title }}</h3>

        @if ($description)
            <p class="mt-1.5 text-sm text-ink/70">{{ $description }}</p>
        @endif

        @isset($action)
            <div class="mt-5">
                {{ $action }}
            </div>
        @endisset
    </div>
</div>
