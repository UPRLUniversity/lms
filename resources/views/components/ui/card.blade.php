@props([
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'rounded-xl bg-card border border-line shadow-sm overflow-hidden']) }}>
    @isset($header)
        <div class="px-5 py-4 border-b border-line">
            {{ $header }}
        </div>
    @endisset

    <div @class(['px-5 py-5' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="px-5 py-4 border-t border-line bg-surface/60">
            {{ $footer }}
        </div>
    @endisset
</div>
