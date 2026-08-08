<div>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="font-display text-lg font-semibold text-ink">Curriculum</h3>
            <p class="text-sm text-ink/65">
                Lessons, quizzes and assignments share one order. Drag a row by its handle — or focus it and
                press <kbd class="rounded border border-line bg-surface px-1 text-xs">Alt</kbd> +
                <kbd class="rounded border border-line bg-surface px-1 text-xs">↑</kbd>/<kbd class="rounded border border-line bg-surface px-1 text-xs">↓</kbd>.
                Changes save automatically.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button variant="ghost" size="sm" :href="route('courses.changes', $course)">
                <x-ui.icon name="arrow-path" class="h-4 w-4" /> Change history
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('assessments.insights', $course)">
                <x-ui.icon name="sparkles" class="h-4 w-4" /> Insights
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('questions.index', $course)">
                <x-ui.icon name="clipboard" class="h-4 w-4" /> Question bank
            </x-ui.button>
            <x-ui.button variant="ghost" size="sm" :href="route('rubrics.index')">
                <x-ui.icon name="list" class="h-4 w-4" /> Rubrics
            </x-ui.button>
        </div>
    </div>

    @include('courses.partials._curriculum_impact_strip')

    {{-- Reorder announcements for screen readers: drag has no natural narration, and the
         keyboard path needs to confirm the move landed. Lives outside x-ref="outline" so
         a partial re-render can't swallow the message mid-announcement. --}}
    <p x-ref="live" role="status" aria-live="polite" class="sr-only"></p>

    {{-- Outline region — delegated clicks survive partial re-renders. --}}
    <div class="mt-4" id="curriculum-region" @click="onCurriculumClick($event)" @keydown="onCurriculumKeydown($event)">
        <div x-ref="outline">
            @include('courses.partials._curriculum')
        </div>

        @if ($canManage)
            {{-- Add module --}}
            <form class="mt-4 flex flex-col gap-2 rounded-xl border border-dashed border-line bg-card p-3 sm:flex-row sm:items-center"
                  @submit.prevent="addModule($event)">
                <input type="text" name="title" x-model="newModuleTitle" required maxlength="200"
                       class="block w-full flex-1 rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                       placeholder="New module title…">
                <x-ui.button type="submit" variant="secondary">
                    <x-ui.icon name="plus" class="h-5 w-5" /> Add module
                </x-ui.button>
            </form>

            <div class="mt-3 flex flex-wrap gap-2">
                <x-ui.button variant="ghost" size="sm" @click="openAssessmentModal()">
                    <x-ui.icon name="clipboard" class="h-4 w-4" /> Add quiz
                </x-ui.button>
                <x-ui.button variant="ghost" size="sm" @click="openAssignmentModal()">
                    <x-ui.icon name="document-text" class="h-4 w-4" /> Add assignment
                </x-ui.button>
            </div>
        @endif
    </div>

    {{-- Add-assignment modal --}}
    @if ($canManage)
        <x-ui.modal name="add-assignment" title="New assignment">
            <form method="POST" action="{{ route('assignments.store', $course) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="insert_at" :value="insert.index ?? ''">

                <div>
                    <label for="as_title" class="block text-sm font-medium text-ink">Title</label>
                    <input id="as_title" name="title" required maxlength="255"
                           class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                           placeholder="e.g. Crisis communication case study">
                </div>

                {{-- Hidden, not removed, when the slot already decides the bucket: the
                     select is still what carries module_id, bound to the same state. --}}
                <div x-show="insert.index === null" x-cloak>
                    <label for="as_module" class="block text-sm font-medium text-ink">Placement</label>
                    <select id="as_module" name="module_id" x-model="newAssignment.moduleId"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="">Course level (end of course)</option>
                        @foreach ($course->modules as $m)
                            <option value="{{ $m->id }}">In “{{ $m->title }}”</option>
                        @endforeach
                    </select>
                </div>
                <p x-show="insert.index !== null" x-cloak class="rounded-xl bg-surface px-3 py-2 text-sm text-ink/65">
                    It'll be added exactly where you clicked in the outline.
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="ghost" @click="$dispatch('close-modal', 'add-assignment')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create &amp; write the brief</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Add-assessment modal --}}
    @if ($canManage)
        <x-ui.modal name="add-assessment" title="New quiz">
            <form method="POST" action="{{ route('assessments.store', $course) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="insert_at" :value="insert.index ?? ''">
                <input type="hidden" name="placement" :value="newAssessment.placement">

                <div>
                    <label for="a_title" class="block text-sm font-medium text-ink">Title</label>
                    <input id="a_title" name="title" required maxlength="255"
                           class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                           placeholder="e.g. Module 1 check">
                </div>

                <div x-show="insert.index === null" x-cloak>
                    <label for="a_placement" class="block text-sm font-medium text-ink">Placement</label>
                    <select id="a_placement" x-model="newAssessment.placement"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="standalone">Course level (end of course)</option>
                        <option value="pre_module">Before a module's lessons</option>
                        <option value="post_module">After a module's lessons</option>
                    </select>
                </div>

                <div x-show="insert.index === null && newAssessment.placement !== 'standalone'" x-cloak>
                    <label for="a_module" class="block text-sm font-medium text-ink">Module</label>
                    <select id="a_module" x-model="newAssessment.moduleId"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach ($course->modules as $m)
                            <option value="{{ $m->id }}">{{ $m->title }}</option>
                        @endforeach
                    </select>
                    @if ($course->modules->isEmpty())
                        <p class="mt-1 text-xs text-crimson">Add a module first to attach a quiz to one.</p>
                    @endif
                </div>
                <input type="hidden" name="module_id" :value="newAssessment.placement === 'standalone' ? '' : newAssessment.moduleId">

                <p x-show="insert.index !== null" x-cloak class="rounded-xl bg-surface px-3 py-2 text-sm text-ink/65">
                    It'll be added exactly where you clicked in the outline.
                </p>

                <div>
                    <label for="a_mode" class="block text-sm font-medium text-ink">Question selection</label>
                    <select id="a_mode" name="selection_mode"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="fixed">Fixed set</option>
                        <option value="pooled">Random pool</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="ghost" @click="$dispatch('close-modal', 'add-assessment')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create &amp; build</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
