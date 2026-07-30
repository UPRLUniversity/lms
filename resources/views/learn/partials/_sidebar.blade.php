@php
    /** @var \App\Models\Course $course */
    /** @var \App\Support\Learning\CourseProgress $snapshot */
    /** @var \App\Support\Learning\CurriculumOutline $outline */
    /** @var \App\Models\Lesson $lesson current */
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
        <div class="mt-0.5 flex items-center justify-between gap-2">
            <p class="text-xs text-ink/50">{{ $course->code }}</p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <a href="{{ route('learn.announcements', $course) }}"
                   class="inline-flex items-center gap-1 text-xs font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                    <x-ui.icon name="megaphone" class="h-3.5 w-3.5" /> Announcements
                </a>
                <a href="{{ route('forum.index', $course) }}"
                   class="inline-flex items-center gap-1 text-xs font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                    <x-ui.icon name="chat-group" class="h-3.5 w-3.5" /> Forum
                </a>
                <a href="{{ route('learn.grades', $course) }}"
                   class="inline-flex items-center gap-1 text-xs font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                    <x-ui.icon name="clipboard-check" class="h-3.5 w-3.5" /> Grades
                </a>
            </div>
        </div>

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
            <p class="mt-1.5 text-[11px] text-ink/45">
                {{ $snapshot->lessonCompletedCount() }} of {{ $snapshot->lessonTotal() }} lessons
                @if (($snapshot->requiredAssessmentTotal ?? 0) > 0)
                    · {{ $snapshot->requiredAssessmentComplete }} of {{ $snapshot->requiredAssessmentTotal }} assessments
                @endif
                @if (($snapshot->requiredAssignmentTotal ?? 0) > 0)
                    · {{ $snapshot->requiredAssignmentComplete }} of {{ $snapshot->requiredAssignmentTotal }} assignments
                @endif
                complete
            </p>
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

                {{-- One merged ladder per module — lessons, quizzes and assignments in
                     exactly the order the instructor dragged them into (Section 14). --}}
                <ul x-show="open" x-collapse class="mt-0.5 space-y-0.5 pl-2">
                    @foreach ($outline->forModule($module->id) as $item)
                        @include('learn.partials._sidebar_'.$item->kind, ['item' => $item])
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- The course-level bucket closes the outline. --}}
        @if ($outline->standalone()->isNotEmpty())
            <div class="mt-1">
                <p class="px-2.5 py-2 text-xs font-semibold uppercase tracking-wide text-ink/40">Assessments &amp; assignments</p>
                <ul class="space-y-0.5 pl-2">
                    @foreach ($outline->standalone() as $item)
                        @include('learn.partials._sidebar_'.$item->kind, ['item' => $item])
                    @endforeach
                </ul>
            </div>
        @endif
    </nav>
</aside>
