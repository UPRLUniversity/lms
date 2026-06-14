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
</div>
