@php
    // Edge slots sit against the module header or the footer, which already draw their
    // own border — a second hairline there reads as a rendering glitch.
    $edge = $edge ?? false;

    // The trailing slot renders OUTSIDE the <ul> (a <div>, not an <li>) so the list can
    // be element-empty when the bucket is — the shape Sortable needs to accept a drop
    // into an empty module. It names its bucket instead of being found by ancestry.
    $tag = $tag ?? 'li';
    $bucket = $bucket ?? null;
@endphp

{{--
    A hairline "insert here" slot between two rows (and at each end of a bucket).

    It carries no index of its own: the builder counts the [data-curriculum-item] rows
    before it at click time, so the slot stays correct after a drag without re-rendering.

    The hairline runs unbroken and the button floats on top of it, so the row separator
    stays continuous while the affordance is invisible. Faint until the list is hovered
    or the button is focused, so a long outline doesn't become a wall of plus signs.
--}}
<{{ $tag }} data-insert-slot @if ($bucket !== null) data-bucket="{{ $bucket }}" @endif class="px-3">
    <div class="relative flex h-4 items-center">
        @unless ($edge)
            <span class="h-px w-full bg-line/70" aria-hidden="true"></span>
        @endunless

        <button type="button" data-action="insert-here" aria-haspopup="true" aria-expanded="false"
                {{-- Always visible on touch, where there is no hover to reveal it. --}}
                class="absolute left-1/2 inline-flex h-5 w-5 -translate-x-1/2 items-center justify-center rounded-full border border-line bg-card text-ink/65 opacity-100 transition hover:border-crimson/40 hover:text-crimson focus-visible:opacity-100 group-hover/list:opacity-100 motion-reduce:transition-none sm:opacity-0"
                aria-label="Insert a lesson, quiz or assignment here">
            <x-ui.icon name="plus" class="h-3 w-3" />
        </button>
    </div>

    <div data-insert-menu hidden class="flex flex-wrap items-center justify-center gap-1.5 pb-2">
        <button type="button" data-action="insert-lesson"
                class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-card px-2.5 py-1.5 text-sm text-ink shadow-sm hover:border-ink/25 focus-ring">
            <x-ui.icon name="play" class="h-4 w-4 text-ink/65" /> Lesson
        </button>
        <button type="button" data-action="insert-assessment"
                class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-card px-2.5 py-1.5 text-sm text-ink shadow-sm hover:border-ink/25 focus-ring">
            <x-ui.icon name="clipboard" class="h-4 w-4 text-gold-ink" /> Quiz
        </button>
        <button type="button" data-action="insert-assignment"
                class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-card px-2.5 py-1.5 text-sm text-ink shadow-sm hover:border-ink/25 focus-ring">
            <x-ui.icon name="document-text" class="h-4 w-4 text-crimson" /> Assignment
        </button>
    </div>
</{{ $tag }}>
