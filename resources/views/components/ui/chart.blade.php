@props([
    'type',            // 'bar' | 'line' | 'doughnut' | ...
    'data',            // Chart.js data object (php array)
    'options' => [],   // Chart.js options object (php array)
    'height' => 260,   // canvas height in px (container is responsive width)
    'label' => null,   // accessible description of the chart
])

{{--
    Branded Chart.js canvas. The config is serialised to a data attribute and
    picked up by resources/js/charts.js, which resolves named brand tones
    ("crimson", "green", "gold", "ink") to the live CSS custom properties and
    respects prefers-reduced-motion. A canvas is not readable by assistive tech,
    so we expose a role/label and keep the underlying figures available in the
    surrounding markup (tables/legends) for screen readers.
--}}
<div {{ $attributes->merge(['class' => 'relative w-full']) }} style="height: {{ (int) $height }}px">
    <canvas
        data-chart='@json(['type' => $type, 'data' => $data, 'options' => $options])'
        role="img"
        @if ($label) aria-label="{{ $label }}" @else aria-hidden="true" @endif
    ></canvas>
</div>
