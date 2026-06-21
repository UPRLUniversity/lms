<div>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="font-display text-lg font-semibold text-ink">Curriculum</h3>
            <p class="text-sm text-ink/60">Drag to reorder. Click a lesson to edit it. Changes save automatically.</p>
        </div>
    </div>

    {{-- Outline region — delegated clicks survive partial re-renders. --}}
    <div class="mt-4" id="curriculum-region" @click="onCurriculumClick($event)">
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
        @endif
    </div>

    {{-- Assessments — quizzes & exams attached to the curriculum --}}
    <div class="mt-8 border-t border-line pt-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">Assessments</h3>
                <p class="text-sm text-ink/60">Quizzes and exams. Pre/post-module assessments power knowledge-gain insights.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button variant="ghost" size="sm" :href="route('assessments.insights', $course)">
                    <x-ui.icon name="sparkles" class="h-4 w-4" /> Insights
                </x-ui.button>
                <x-ui.button variant="ghost" size="sm" :href="route('questions.index', $course)">
                    <x-ui.icon name="clipboard" class="h-4 w-4" /> Question bank
                </x-ui.button>
                @if ($canManage)
                    <x-ui.button size="sm" @click="$dispatch('open-modal', 'add-assessment')">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Add assessment
                    </x-ui.button>
                @endif
            </div>
        </div>

        @if ($course->assessments->isEmpty())
            <div class="mt-4">
                <x-ui.empty-state icon="clipboard" title="No assessments yet"
                    description="Add a quiz or exam — attach it before or after a module, or as a standalone." />
            </div>
        @else
            <ul class="mt-4 space-y-2">
                @foreach ($course->assessments as $assessment)
                    @php $module = $assessment->module_id ? $course->modules->firstWhere('id', $assessment->module_id) : null; @endphp
                    <li>
                        <a href="{{ route('assessments.edit', [$course, $assessment]) }}"
                           class="flex items-center gap-3 rounded-xl border border-line bg-card p-3 transition hover:border-ink/20 focus-ring">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gold/10 text-gold-ink">
                                <x-ui.icon name="clipboard" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-ink">{{ $assessment->title }}</span>
                                <span class="text-xs text-ink/50">
                                    {{ $assessment->placement->label() }}@if ($module) · {{ $module->title }}@endif · {{ $assessment->questionCount() }} questions
                                </span>
                            </span>
                            <x-ui.badge :variant="$assessment->status->badge()">{{ $assessment->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Add-assessment modal --}}
    @if ($canManage)
        <x-ui.modal name="add-assessment" title="New assessment">
            <form method="POST" action="{{ route('assessments.store', $course) }}" class="space-y-4"
                  x-data="{ placement: 'standalone' }">
                @csrf
                <div>
                    <label for="a_title" class="block text-sm font-medium text-ink">Title</label>
                    <input id="a_title" name="title" required maxlength="255"
                           class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                           placeholder="e.g. Module 1 check">
                </div>
                <div>
                    <label for="a_placement" class="block text-sm font-medium text-ink">Placement</label>
                    <select id="a_placement" name="placement" x-model="placement"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="standalone">Standalone (course-level)</option>
                        <option value="pre_module">Pre-module (before lessons)</option>
                        <option value="post_module">Post-module (after lessons)</option>
                    </select>
                </div>
                <div x-show="placement !== 'standalone'" x-cloak>
                    <label for="a_module" class="block text-sm font-medium text-ink">Module</label>
                    <select id="a_module" name="module_id"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach ($course->modules as $m)
                            <option value="{{ $m->id }}">{{ $m->title }}</option>
                        @endforeach
                    </select>
                    @if ($course->modules->isEmpty())
                        <p class="mt-1 text-xs text-crimson">Add a module first to attach a pre/post assessment.</p>
                    @endif
                </div>
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
