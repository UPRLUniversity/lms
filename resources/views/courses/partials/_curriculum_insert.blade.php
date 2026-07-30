{{--
    A hairline "insert here" slot between two rows (and at each end of a bucket).

    It carries no index of its own: the builder counts the [data-curriculum-item] rows
    before it at click time, so the slot stays correct after a drag without re-rendering.
    Faint until the list is hovered or the button is focused, so a long outline doesn't
    turn into a wall of plus signs.
--}}
<li data-insert-slot class="relative flex items-center px-3 py-0.5">
    <span class="h-px flex-1 bg-line/70" aria-hidden="true"></span>

    <button type="button" data-action="insert-here" aria-haspopup="menu" aria-expanded="false"
            class="mx-2 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-line bg-card text-ink/40 opacity-60 transition hover:border-crimson/40 hover:text-crimson focus-visible:opacity-100 group-hover/list:opacity-100 motion-reduce:transition-none sm:opacity-0"
            aria-label="Insert a lesson, quiz or assignment here">
        <x-ui.icon name="plus" class="h-3 w-3" />
    </button>

    <span class="h-px flex-1 bg-line/70" aria-hidden="true"></span>

    <div data-insert-menu hidden role="menu"
         class="absolute left-1/2 top-6 z-20 w-44 -translate-x-1/2 rounded-xl border border-line bg-card p-1 shadow-lg">
        <button type="button" role="menuitem" data-action="insert-lesson"
                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm text-ink hover:bg-surface focus-ring">
            <x-ui.icon name="play" class="h-4 w-4 text-ink/50" /> Lesson
        </button>
        <button type="button" role="menuitem" data-action="insert-assessment"
                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm text-ink hover:bg-surface focus-ring">
            <x-ui.icon name="clipboard" class="h-4 w-4 text-gold-ink" /> Quiz
        </button>
        <button type="button" role="menuitem" data-action="insert-assignment"
                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm text-ink hover:bg-surface focus-ring">
            <x-ui.icon name="document-text" class="h-4 w-4 text-crimson" /> Assignment
        </button>
    </div>
</li>
