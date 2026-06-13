{{--
    UPRL sunburst motif — a subtle decorative accent for auth panels, empty
    states and certificates. Purely decorative, so hidden from assistive tech.
    Colour is inherited via currentColor; size/opacity via the class attribute.
--}}
@props(['rays' => 24])

<svg {{ $attributes->merge(['class' => 'pointer-events-none select-none']) }}
     viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"
     aria-hidden="true" focusable="false">
    <g stroke="currentColor" stroke-width="2" stroke-linecap="round">
        @for ($i = 0; $i < $rays; $i++)
            @php
                $angle = ($i / $rays) * 360;
                $inner = $i % 2 === 0 ? 48 : 40;
                $outer = $i % 2 === 0 ? 96 : 82;
                $rad = deg2rad($angle);
                $x1 = 100 + $inner * cos($rad);
                $y1 = 100 + $inner * sin($rad);
                $x2 = 100 + $outer * cos($rad);
                $y2 = 100 + $outer * sin($rad);
            @endphp
            <line x1="{{ number_format($x1, 2) }}" y1="{{ number_format($y1, 2) }}"
                  x2="{{ number_format($x2, 2) }}" y2="{{ number_format($y2, 2) }}" />
        @endfor
    </g>
    <circle cx="100" cy="100" r="30" stroke="currentColor" stroke-width="2" />
    <circle cx="100" cy="100" r="14" fill="currentColor" />
</svg>
