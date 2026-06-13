@props([
    'html' => null,
])

{{--
    The single render path for stored rich HTML. Content is already sanitized on
    save (RichHtml cast), so it is emitted raw, but only ever through this
    brand-typographic container.
--}}
<div {{ $attributes->merge(['class' => 'uprl-prose']) }}>
    {!! $html ?? $slot !!}
</div>
