@php
    use Illuminate\Support\Str;

    $canManage = $canManage ?? false;

    $fmt = function (int $minutes): ?string {
        if ($minutes <= 0) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m';
    };

    $courseMinutes = $course->modules->sum(fn ($m) => $m->lessons->sum('duration_minutes'));
    $courseLessons = $course->modules->sum(fn ($m) => $m->lessons->count());
@endphp

<div data-curriculum>
    {{-- Whole-course totals --}}
    <div class="mb-3 flex items-center justify-between px-1 text-sm text-ink/60">
        <span>{{ $course->modules->count() }} {{ Str::plural('module', $course->modules->count()) }} · {{ $courseLessons }} {{ Str::plural('lesson', $courseLessons) }}</span>
        @if ($d = $fmt($courseMinutes))
            <span class="inline-flex items-center gap-1"><x-ui.icon name="clock" class="h-4 w-4" /> {{ $d }} total</span>
        @endif
    </div>

    @if ($course->modules->isEmpty())
        <x-ui.empty-state
            icon="book"
            title="No modules yet"
            description="Modules group your lessons into sections. Add your first module below to get started." />
    @else
        <ul data-module-list class="space-y-3">
            @foreach ($course->modules as $module)
                @php $moduleMinutes = $module->lessons->sum('duration_minutes'); @endphp
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
                            {{ $module->lessons->count() }} {{ Str::plural('lesson', $module->lessons->count()) }}@if ($d = $fmt($moduleMinutes)) · {{ $d }} @endif
                        </span>

                        @if ($canManage)
                            <button type="button" data-action="delete-module" data-module-id="{{ $module->id }}"
                                    class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Delete module">
                                <x-ui.icon name="trash" class="h-4 w-4" />
                            </button>
                        @endif
                    </div>

                    {{-- Lessons --}}
                    <div data-module-body>
                        @if ($module->description)
                            <p class="px-4 pt-3 text-sm text-ink/60">{{ $module->description }}</p>
                        @endif

                        <ul data-lesson-list data-module-id="{{ $module->id }}" class="divide-y divide-line">
                            @forelse ($module->lessons as $lesson)
                                <li data-lesson data-lesson-id="{{ $lesson->id }}" class="flex items-center gap-2 px-3 py-2.5 hover:bg-surface/40">
                                    @if ($canManage)
                                        <button type="button" data-drag-lesson class="cursor-grab rounded-lg p-1.5 text-ink/30 hover:text-ink/60 focus-ring" aria-label="Drag to reorder lesson" title="Drag to reorder">
                                            <x-ui.icon name="arrows-up-down" class="h-4 w-4" />
                                        </button>
                                    @endif

                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ink/5 text-ink/60">
                                        <x-ui.icon :name="$lesson->type->icon()" class="h-4 w-4" />
                                    </span>

                                    <button type="button" @if ($canManage) data-action="edit-lesson" data-lesson-id="{{ $lesson->id }}" @endif
                                            class="min-w-0 flex-1 text-left focus-ring rounded {{ $canManage ? 'cursor-pointer' : 'cursor-default' }}">
                                        <span class="block truncate text-sm font-medium text-ink">{{ $lesson->title }}</span>
                                        <span class="text-xs text-ink/50">{{ $lesson->type->label() }}</span>
                                    </button>

                                    @if ($lesson->is_free_preview)
                                        <x-ui.badge variant="success" class="hidden sm:inline-flex">Preview</x-ui.badge>
                                    @endif
                                    @if ($d = $fmt((int) $lesson->duration_minutes))
                                        <span class="shrink-0 text-xs text-ink/40">{{ $d }}</span>
                                    @endif

                                    @if ($canManage)
                                        <button type="button" data-action="delete-lesson" data-lesson-id="{{ $lesson->id }}"
                                                class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Delete lesson">
                                            <x-ui.icon name="trash" class="h-4 w-4" />
                                        </button>
                                    @endif
                                </li>
                            @empty
                                <li class="px-4 py-3 text-sm text-ink/40">No lessons yet.</li>
                            @endforelse
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
</div>
