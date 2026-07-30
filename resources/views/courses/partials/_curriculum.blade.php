@php
    use App\Services\Courses\CurriculumOrderService;
    use Illuminate\Support\Str;

    $canManage = $canManage ?? false;

    // One merged ladder per bucket — the same merge LearningService feeds the player,
    // so what an author drags is exactly what a learner walks through.
    $order = app(CurriculumOrderService::class);

    $fmt = function (int $minutes): ?string {
        if ($minutes <= 0) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m';
    };

    $courseMinutes = $course->modules->sum(fn ($m) => $m->lessons->sum('duration_minutes'));
    $courseLessons = $course->modules->sum(fn ($m) => $m->lessons->count());

    $courseLevel = $order->merge(
        [],
        $course->assessments->whereNull('module_id'),
        $course->assignments->whereNull('module_id'),
    );
@endphp

<div data-curriculum>
    {{-- Whole-course totals --}}
    <div class="mb-3 flex items-center justify-between px-1 text-sm text-ink/60">
        <span>{{ $course->modules->count() }} {{ Str::plural('module', $course->modules->count()) }} · {{ $courseLessons }} {{ Str::plural('lesson', $courseLessons) }}</span>
        @if ($d = $fmt($courseMinutes))
            <span class="inline-flex items-center gap-1"><x-ui.icon name="clock" class="h-4 w-4" /> {{ $d }} total</span>
        @endif
    </div>

    @if ($course->modules->isEmpty() && $courseLevel->isEmpty())
        <x-ui.empty-state
            icon="book"
            title="No modules yet"
            description="Modules group your lessons, quizzes and assignments into sections. Add your first module below to get started." />
    @else
        <ul data-module-list class="space-y-3">
            @foreach ($course->modules as $module)
                @php
                    $moduleMinutes = $module->lessons->sum('duration_minutes');
                    $items = $order->merge($module->lessons, $module->assessments, $module->assignments);
                @endphp
                <li data-module data-module-id="{{ $module->id }}" class="overflow-hidden rounded-xl border border-line bg-card shadow-sm">
                    {{-- Module header --}}
                    <div class="flex items-center gap-2 border-b border-line bg-surface/40 px-3 py-3">
                        @if ($canManage)
                            <button type="button" data-drag-module class="cursor-grab rounded-lg p-1.5 text-ink/30 hover:text-ink/60 focus-ring" aria-label="Drag to reorder module" title="Drag to reorder">
                                <x-ui.icon name="arrows-up-down" class="h-4 w-4" />
                            </button>
                        @endif

                        <button type="button" data-action="toggle-module" class="rounded-lg p-1 text-ink/40 hover:text-ink focus-ring" aria-label="Collapse or expand module">
                            <x-ui.icon name="chevron-right" class="h-4 w-4 transition-transform" data-chevron />
                        </button>

                        <span class="min-w-0 flex-1">
                            <span
                                @if ($canManage) contenteditable="plaintext-only" data-action="rename-module" data-module-id="{{ $module->id }}" @endif
                                class="block truncate rounded font-display font-semibold text-ink focus:outline-none focus:ring-2 focus:ring-crimson focus:ring-offset-1"
                            >{{ $module->title }}</span>
                        </span>

                        <span class="hidden shrink-0 text-xs text-ink/50 sm:inline">
                            {{ $items->count() }} {{ Str::plural('item', $items->count()) }}@if ($d = $fmt($moduleMinutes)) · {{ $d }} @endif
                        </span>

                        @if ($canManage)
                            <button type="button" data-action="delete-module" data-module-id="{{ $module->id }}"
                                    class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Delete module">
                                <x-ui.icon name="trash" class="h-4 w-4" />
                            </button>
                        @endif
                    </div>

                    {{-- The module's items: lessons, quizzes and assignments in one order --}}
                    <div data-module-body>
                        @if ($module->description)
                            <p class="px-4 pt-3 text-sm text-ink/60">{{ $module->description }}</p>
                        @endif

                        <ul data-item-list data-module-id="{{ $module->id }}" class="group/list {{ $canManage ? '' : 'divide-y divide-line' }}">
                            @forelse ($items as $item)
                                @if ($canManage)
                                    @include('courses.partials._curriculum_insert')
                                @endif
                                @include('courses.partials._curriculum_item', ['item' => $item])
                            @empty
                                <li class="px-4 py-3 text-sm text-ink/40">Nothing in this module yet.</li>
                            @endforelse

                            @if ($canManage)
                                @include('courses.partials._curriculum_insert')
                            @endif
                        </ul>

                        @if ($canManage)
                            <div class="border-t border-line px-3 py-2">
                                <button type="button" data-action="add-lesson" data-module-id="{{ $module->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-crimson hover:bg-crimson/5 focus-ring">
                                    <x-ui.icon name="plus" class="h-4 w-4" /> Add lesson
                                </button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- The course-level bucket: quizzes and assignments that belong to no module. It
         renders with the same affordances, so an item can be dragged in or out of it. --}}
    @if ($canManage || $courseLevel->isNotEmpty())
        <div class="mt-3 overflow-hidden rounded-xl border border-dashed border-line bg-card">
            <div class="flex items-center gap-2 border-b border-line bg-surface/40 px-3 py-3">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ink/5 text-ink/50">
                    <x-ui.icon name="layers" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-display font-semibold text-ink">Course level</span>
                    <span class="block text-xs text-ink/50">Final exams and coursework that sit outside any module — shown at the end of the course.</span>
                </span>
            </div>

            <ul data-item-list data-module-id="" class="group/list {{ $canManage ? '' : 'divide-y divide-line' }}">
                @forelse ($courseLevel as $item)
                    @if ($canManage)
                        @include('courses.partials._curriculum_insert')
                    @endif
                    @include('courses.partials._curriculum_item', ['item' => $item])
                @empty
                    <li class="px-4 py-3 text-sm text-ink/40">Nothing at course level — drag a quiz or assignment here, or use the + below.</li>
                @endforelse

                @if ($canManage)
                    @include('courses.partials._curriculum_insert')
                @endif
            </ul>
        </div>
    @endif
</div>
