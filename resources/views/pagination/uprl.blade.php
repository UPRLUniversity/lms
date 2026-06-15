@php
    // Windowed page numbers around the current page.
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
    $range = $paginator->getUrlRange($start, $end);

    $base = 'inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-lg px-3 text-sm font-medium focus-ring';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink/60">
            Showing <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-ink">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-ink">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $base }} cursor-not-allowed text-ink/30" aria-disabled="true">{{ __('Prev') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" data-nav rel="prev"
                   class="{{ $base }} text-ink/70 hover:bg-ink/5 hover:text-ink">{{ __('Prev') }}</a>
            @endif

            {{-- Leading ellipsis --}}
            @if ($start > 1)
                <a href="{{ $paginator->url(1) }}" data-nav class="{{ $base }} text-ink/70 hover:bg-ink/5 hover:text-ink">1</a>
                @if ($start > 2)
                    <span class="{{ $base }} text-ink/30">…</span>
                @endif
            @endif

            {{-- Window --}}
            @foreach ($range as $page => $url)
                @if ($page == $current)
                    <span aria-current="page" class="{{ $base }} bg-crimson text-white">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" data-nav class="{{ $base }} text-ink/70 hover:bg-ink/5 hover:text-ink">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Trailing ellipsis --}}
            @if ($end < $last)
                @if ($end < $last - 1)
                    <span class="{{ $base }} text-ink/30">…</span>
                @endif
                <a href="{{ $paginator->url($last) }}" data-nav class="{{ $base }} text-ink/70 hover:bg-ink/5 hover:text-ink">{{ $last }}</a>
            @endif

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" data-nav rel="next"
                   class="{{ $base }} text-ink/70 hover:bg-ink/5 hover:text-ink">{{ __('Next') }}</a>
            @else
                <span class="{{ $base }} cursor-not-allowed text-ink/30" aria-disabled="true">{{ __('Next') }}</span>
            @endif
        </div>
    </nav>
@endif
