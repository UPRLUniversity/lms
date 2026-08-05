@props([
    // [['label' => 'Courses', 'href' => route(...)], ['label' => 'Current page']]
    // The LAST item is the current page and is never a link — a link to where you
    // already are is noise, and screen readers announce aria-current instead.
    'items' => [],
])

@php
    $items = array_values(array_filter($items));
    $last = count($items) - 1;
@endphp

@if ($items)
    <nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'min-w-0']) }}>
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-ink/60">
            @foreach ($items as $i => $item)
                <li class="flex min-w-0 items-center gap-1.5">
                    @if ($i > 0)
                        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink/30" />
                    @endif

                    @if ($i === $last || empty($item['href']))
                        <span @class(['truncate', 'font-medium text-ink' => $i === $last])
                              @if ($i === $last) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </span>
                    @else
                        <a href="{{ $item['href'] }}"
                           class="truncate rounded hover:text-ink hover:underline underline-offset-2 focus-ring">
                            {{ $item['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
