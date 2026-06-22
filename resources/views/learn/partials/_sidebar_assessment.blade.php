@php
    /** @var \App\Support\Learning\CurriculumItem $item */
    /** @var \App\Models\Course $course */
    $assessment = $item->model;
    $label = $assessment->placement->label();
@endphp

<li>
    @if ($item->locked)
        <div class="flex cursor-not-allowed items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-ink/35"
             title="Complete the previous step to unlock">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <span class="min-w-0 flex-1 truncate">{{ $assessment->title }}</span>
        </div>
    @else
        <a href="{{ route('assessments.start', [$course, $assessment]) }}"
           class="group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-ink/75 transition-colors hover:bg-ink/5 focus-ring">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                @if ($item->completed)
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-success text-white">
                        <x-ui.icon name="check" class="h-3 w-3" stroke-width="3" />
                    </span>
                @else
                    <span class="text-gold-ink"><x-ui.icon name="clipboard" class="h-4 w-4" /></span>
                @endif
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate">{{ $assessment->title }}</span>
                <span class="text-[11px] text-ink/40">{{ $label }} assessment</span>
            </span>
        </a>
    @endif
</li>
