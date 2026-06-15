@php
    use App\Enums\LessonProgressStatus;

    /** @var \App\Models\Course $course */
    /** @var \App\Support\Learning\CourseProgress $snapshot */
    /** @var \App\Models\Lesson $lesson current */

    $fmtDur = function (?int $minutes): ?string {
        if (! $minutes || $minutes <= 0) return null;
        $h = intdiv($minutes, 60); $m = $minutes % 60;
        return $h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m';
    };
@endphp

{{-- Curriculum sidebar: fixed on desktop, slide-in drawer on mobile. --}}
<aside
    class="fixed inset-y-0 left-0 z-40 flex w-80 max-w-[85vw] flex-col border-r border-line bg-card transition-transform duration-200"
    :class="{
        '-translate-x-full lg:-translate-x-full': collapsed && ! drawer,
        '-translate-x-full lg:translate-x-0': ! collapsed && ! drawer,
        'translate-x-0': drawer,
    }"
    x-on:keydown.escape.window="drawer = false"
    aria-label="Course curriculum">

    {{-- Header: course + progress --}}
    <div class="shrink-0 border-b border-line p-5">
        <div class="flex items-start justify-between gap-2">
            <a href="{{ route('learning.index') }}"
               class="group inline-flex items-center gap-1.5 text-xs font-medium text-ink/50 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" /> My Learning
            </a>
            {{-- Close drawer (mobile only) --}}
            <button type="button" @click="drawer = false"
                    class="-mr-1 rounded-lg p-1 text-ink/40 hover:bg-ink/5 hover:text-ink focus-ring lg:hidden"
                    aria-label="Close curriculum">
                <x-ui.icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <h1 class="mt-2 font-display text-lg font-semibold leading-snug text-ink">{{ $course->title }}</h1>
        <p class="mt-0.5 text-xs text-ink/50">{{ $course->code }}</p>

        {{-- Overall progress --}}
        <div class="mt-4">
            <div class="flex items-center justify-between text-xs font-medium">
                <span class="text-ink/60">Your progress</span>
                <span class="text-crimson" x-text="percent + '%'">{{ $snapshot->percent() }}%</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-ink/5" role="progressbar"
                 :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" aria-label="Course progress">
                <div class="h-full rounded-full bg-crimson transition-[width] duration-500 ease-out"
                     :style="`width: ${percent}%`" style="width: {{ $snapshot->percent() }}%"></div>
            </div>
            <p class="mt-1.5 text-[11px] text-ink/45">{{ $snapshot->completedCount() }} of {{ $snapshot->total() }} lessons complete</p>
        </div>
    </div>

    {{-- Modules + lessons --}}
    <nav class="flex-1 overflow-y-auto p-3" aria-label="Lessons">
        @foreach ($course->modules as $module)
            @php $moduleHasCurrent = $module->lessons->contains('id', $lesson->id); @endphp
            <div x-data="{ open: {{ $moduleHasCurrent ? 'true' : 'false' }} }" class="mb-1">
                <button type="button" @click="open = ! open"
                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left focus-ring"
                        :aria-expanded="open.toString()">
                    <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 text-ink/40 transition-transform" ::class="open && 'rotate-90'" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-ink">{{ $module->title }}</span>
                    </span>
                    @if ($snapshot->isModuleComplete($module))
                        <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-success text-white" title="Module complete">
                            <x-ui.icon name="check" class="h-2.5 w-2.5" stroke-width="3" />
                        </span>
                    @endif
                </button>

                <ul x-show="open" x-collapse class="mt-0.5 space-y-0.5 pl-2">
                    @foreach ($module->lessons as $item)
                        @php
                            $isCurrent = $item->id === $lesson->id;
                            $locked = $snapshot->isLocked($item);
                            $done = $snapshot->isComplete($item);
                            $dur = $fmtDur($item->duration_minutes);
                        @endphp
                        <li>
                            @if ($locked)
                                {{-- Sequential-locked: not a link --}}
                                <div class="group flex cursor-not-allowed items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-ink/35"
                                     title="Complete the previous lesson to unlock">
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                        <x-ui.icon name="lock" class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0 flex-1 truncate">{{ $item->title }}</span>
                                    @if ($dur)<span class="shrink-0 text-[11px]">{{ $dur }}</span>@endif
                                </div>
                            @else
                                <a href="{{ route('learn.show', [$course, $item]) }}"
                                   @class([
                                       'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm transition-colors focus-ring',
                                       'bg-crimson/10 font-medium text-crimson' => $isCurrent,
                                       'text-ink/75 hover:bg-ink/5' => ! $isCurrent,
                                   ])
                                   @if ($isCurrent) aria-current="true" @endif>
                                    {{-- State icon (reactive to live completion) --}}
                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center">
                                        {{-- Completed tick --}}
                                        <span x-show="isDone({{ $item->id }})" @if (! $done) x-cloak @endif
                                              class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-success text-white">
                                            <x-ui.icon name="check" class="h-3 w-3" stroke-width="3" />
                                        </span>
                                        {{-- Not complete: current ring / type icon --}}
                                        <span x-show="! isDone({{ $item->id }})" @if ($done) x-cloak @endif>
                                            @if ($isCurrent)
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border-2 border-crimson">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-crimson"></span>
                                                </span>
                                            @else
                                                <span class="text-ink/35"><x-ui.icon :name="$item->type->icon()" class="h-4 w-4" /></span>
                                            @endif
                                        </span>
                                    </span>

                                    <span class="min-w-0 flex-1 truncate">{{ $item->title }}</span>
                                    @if ($dur)<span class="shrink-0 text-[11px] text-ink/40">{{ $dur }}</span>@endif
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
