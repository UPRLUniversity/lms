{{--
    A hairline "insert here" slot between two rows (and at each end of a bucket).

    It carries no index of its own: the builder counts the [data-curriculum-item] rows
    before it at click time, so the slot stays correct after a drag without re-rendering.
    Faint until the list is hovered or the button is focused, so a long outline doesn't
    turn into a wall of plus signs.

    The menu expands the slot inline rather than floating over the list: the module card
    clips its overflow to keep its rounded corners, which would cut off a popover opening
    near the bottom of a module.
--}}
<li data-insert-slot class="px-3">
    {{-- The hairline doubles as the row separator, which is why the item lists carry no
         `divide-y` while these slots are rendered. --}}
    <div class="flex h-4 items-center">
        <span class="h-px flex-1 bg-line/70" aria-hidden="true"></span>

        <button type="button" data-action="insert-here" aria-haspopup="true" aria-expanded="false"
                class="mx-2 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-line bg-card text-ink/40 opacity-60 transition hover:border-crimson/40 hover:text-crimson focus-visible:opacity-100 group-hover/list:opacity-100 motion-reduce:transition-none sm:opacity-0"
                aria-label="Insert a lesson, quiz or assignment here">
            <x-ui.icon name="plus" class="h-3 w-3" />
        </button>

        <span class="h-px flex-1 bg-line/70" aria-hidden="true"></span>
    </div>

    <div data-insert-menu hidden class="flex flex-wrap items-center justify-center gap-1.5 py-2">
        <button type="button" data-action="insert-lesson"
                class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-card px-2.5 py-1.5 text-sm text-ink shadow-sm hover:border-ink/25 focus-ring">
            <x-ui.icon name="play" class="h-4 w-4 text-ink/50" /> Lesson
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
</li>
