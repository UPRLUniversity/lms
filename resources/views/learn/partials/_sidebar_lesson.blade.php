@php
    /** @var \App\Support\Learning\CurriculumItem $item */
    /** @var \App\Models\Course $course */
    /** @var \App\Models\Lesson $lesson current */
    $model = $item->model;
    $isCurrent = $model->id === $lesson->id;

    $h = intdiv((int) $model->duration_minutes, 60);
    $m = (int) $model->duration_minutes % 60;
    $dur = $model->duration_minutes > 0 ? ($h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m') : null;
@endphp

<li>
    @if ($item->locked)
        {{-- Sequential-locked: not a link --}}
        <div class="group flex cursor-not-allowed items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-ink/35"
             title="Complete the previous step to unlock">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                <x-ui.icon name="lock" class="h-4 w-4" />
            </span>
            <span class="min-w-0 flex-1 truncate">{{ $model->title }}</span>
            @if ($dur)<span class="shrink-0 text-[11px]">{{ $dur }}</span>@endif
        </div>
    @else
        <a href="{{ route('learn.show', [$course, $model]) }}"
           @class([
               'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm transition-colors focus-ring',
               'bg-crimson/10 font-medium text-crimson' => $isCurrent,
               'text-ink/75 hover:bg-ink/5' => ! $isCurrent,
           ])
           @if ($isCurrent) aria-current="true" @endif>
            {{-- State icon (reactive to live completion) --}}
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                {{-- Completed tick --}}
                <span x-show="isDone({{ $model->id }})" @if (! $item->completed) x-cloak @endif
                      class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-success text-white">
                    <x-ui.icon name="check" class="h-3 w-3" stroke-width="3" />
                </span>
                {{-- Not complete: current ring / type icon --}}
                <span x-show="! isDone({{ $model->id }})" @if ($item->completed) x-cloak @endif>
                    @if ($isCurrent)
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-crimson">
                            <span class="h-1.5 w-1.5 rounded-full bg-crimson"></span>
                        </span>
                    @else
                        <span class="text-ink/35"><x-ui.icon :name="$model->type->icon()" class="h-4 w-4" /></span>
                    @endif
                </span>
            </span>

            <span class="min-w-0 flex-1 truncate">{{ $model->title }}</span>
            @if ($dur)<span class="shrink-0 text-[11px] text-ink/40">{{ $dur }}</span>@endif
        </a>
    @endif
</li>
