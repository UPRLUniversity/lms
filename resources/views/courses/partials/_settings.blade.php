@php
    use App\Enums\CourseVisibility;
    use App\Enums\EnrollmentMode;

    $objectives = old('learning_objectives', $course->learning_objectives ?: ['']);
    $currentMode = old('enrollment_mode', $course->enrollment_mode?->value ?? EnrollmentMode::Open->value);
    $windowFmt = fn ($v) => $v?->format('Y-m-d\TH:i');
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    {{-- Publish checklist --}}
    <div class="lg:order-2">
        <div class="rounded-xl border border-line bg-card p-5 shadow-sm">
            <h3 class="font-display font-semibold text-ink">Publish checklist</h3>
            <p class="mt-1 text-xs text-ink/60">A course needs all of these before it can go live.</p>
            <ul class="mt-4 space-y-2.5 text-sm">
                @php
                    $checks = [
                        'At least one module' => ! in_array('Add at least one module.', $publishBlockers, true),
                        'At least one lesson' => ! in_array('Add at least one lesson.', $publishBlockers, true),
                        'A short summary' => ! in_array('Write a short summary.', $publishBlockers, true),
                        'A cover image' => ! in_array('Upload a cover image.', $publishBlockers, true),
                    ];
                @endphp
                @foreach ($checks as $label => $done)
                    <li class="flex items-center gap-2">
                        <span @class([
                            'inline-flex h-5 w-5 items-center justify-center rounded-full',
                            'bg-success/10 text-success' => $done,
                            'bg-ink/5 text-ink/30' => ! $done,
                        ])>
                            <x-ui.icon name="check" class="h-3.5 w-3.5" stroke-width="2.5" />
                        </span>
                        <span class="{{ $done ? 'text-ink/80' : 'text-ink/50' }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Settings form --}}
    <div class="lg:order-1 lg:col-span-2">
        @if (! $canManage)
            <div class="rounded-xl border border-line bg-surface/60 p-4 text-sm text-ink/60">
                You have read-only access to this course.
            </div>
        @endif

        <form method="POST" action="{{ route('courses.update', $course) }}" enctype="multipart/form-data"
              class="space-y-6" @if ($canManage) x-data="courseSettings()" @submit="dirty = false" @endif>
            @csrf
            @method('PUT')
            <fieldset @if (! $canManage) disabled @endif class="space-y-6">

                {{-- Cover with live preview --}}
                <div>
                    <label class="block text-sm font-medium text-ink">Cover image</label>
                    <p class="text-xs text-ink/60">Shown on the catalogue. 1200×630 works best. JPG, PNG or WebP.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-4">
                        <div class="relative aspect-[16/9] w-56 overflow-hidden rounded-xl border border-line bg-gradient-to-br from-crimson to-crimson-dark">
                            <template x-if="coverPreview">
                                <img :src="coverPreview" alt="" class="h-full w-full object-cover">
                            </template>
                            @if ($course->coverUrl())
                                <img x-show="!coverPreview" src="{{ $course->coverUrl() }}" alt="Current cover" class="h-full w-full object-cover">
                            @else
                                <div x-show="!coverPreview" class="absolute inset-0 flex items-center justify-center">
                                    <span class="font-display text-lg font-bold text-white/80">{{ $course->code }}</span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-card px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface focus-within:ring-2 focus-within:ring-crimson">
                                <x-ui.icon name="camera" class="h-5 w-5" /> Choose image
                                <input type="file" name="cover" accept="image/png,image/jpeg,image/webp" class="sr-only"
                                       @change="previewCover($event)">
                            </label>
                            <p class="mt-1 text-xs text-ink/50" x-text="coverName"></p>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('cover')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="title" label="Course title" :value="old('title', $course->title)" required @input="dirty = true" />
                    <x-ui.field name="code" label="Course code" :value="old('code', $course->code)" required @input="dirty = true" />
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <x-ui.field name="level" label="Level" required>
                        <select id="level" name="level" @change="dirty = true"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach ($levels as $level)
                                <option value="{{ $level->value }}" @selected(old('level', $course->level->value) === $level->value)>{{ $level->label() }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="department_id" label="Department" required>
                        <select id="department_id" name="department_id" @change="dirty = true"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) old('department_id', $course->department_id) === $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="duration_minutes" label="Est. minutes" type="number"
                                :value="old('duration_minutes', $course->duration_minutes)" hint="Optional" @input="dirty = true" />
                </div>

                <x-ui.field name="summary" label="Summary" :hint="'A one or two sentence pitch for the catalogue.'">
                    <textarea id="summary" name="summary" rows="2" maxlength="500" @input="dirty = true"
                              class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                              placeholder="e.g. Master the fundamentals of public relations in a Nigerian context.">{{ old('summary', $course->summary) }}</textarea>
                </x-ui.field>

                {{-- Visibility --}}
                <x-ui.field name="visibility" label="Visibility" required>
                    <select id="visibility" name="visibility" @change="dirty = true"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach (CourseVisibility::cases() as $vis)
                            <option value="{{ $vis->value }}" @selected(old('visibility', $course->visibility->value) === $vis->value)>
                                {{ $vis->label() }} — {{ $vis->hint() }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                {{-- Enrolment --}}
                <div class="space-y-5 rounded-xl border border-line bg-surface/40 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-display font-semibold text-ink">Enrolment</h3>
                            <p class="text-xs text-ink/60">How students join, and how many places there are.</p>
                        </div>
                        @if ($course->isPublished())
                            <x-ui.button size="sm" variant="ghost" :href="route('courses.roster', $course)">
                                <x-ui.icon name="users" class="h-4 w-4" /> View roster
                            </x-ui.button>
                        @endif
                    </div>

                    <x-ui.field name="enrollment_mode" label="Enrolment mode" required>
                        <select id="enrollment_mode" name="enrollment_mode" @change="dirty = true"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach (EnrollmentMode::cases() as $mode)
                                <option value="{{ $mode->value }}" @selected($currentMode === $mode->value)>
                                    {{ $mode->label() }} — {{ $mode->hint() }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-ui.field name="capacity" label="Capacity" type="number"
                                    :value="old('capacity', $course->capacity)" hint="Blank = unlimited" @input="dirty = true" />

                        <x-ui.field name="enrollment_opens_at" label="Opens" hint="Optional">
                            <input id="enrollment_opens_at" name="enrollment_opens_at" type="datetime-local" @change="dirty = true"
                                   value="{{ old('enrollment_opens_at', $windowFmt($course->enrollment_opens_at)) }}"
                                   class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </x-ui.field>

                        <x-ui.field name="enrollment_closes_at" label="Closes" hint="Optional">
                            <input id="enrollment_closes_at" name="enrollment_closes_at" type="datetime-local" @change="dirty = true"
                                   value="{{ old('enrollment_closes_at', $windowFmt($course->enrollment_closes_at)) }}"
                                   class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        </x-ui.field>
                    </div>
                </div>

                {{-- Learning objectives --}}
                <div x-data="objectiveRows(@js(array_values($objectives)))" @change="dirty = true">
                    <label class="block text-sm font-medium text-ink">Learning objectives</label>
                    <p class="text-xs text-ink/60">What will a learner be able to do? Add one per row.</p>
                    <div class="mt-2 space-y-2">
                        <template x-for="(row, index) in rows" :key="row.key">
                            <div class="flex items-center gap-2">
                                <span class="text-ink/30"><x-ui.icon name="check" class="h-4 w-4" /></span>
                                <input type="text" :name="'learning_objectives[]'" x-model="row.value" maxlength="255"
                                       class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                       placeholder="e.g. Plan a basic PR campaign">
                                <button type="button" @click="remove(index)" class="rounded-lg p-2 text-ink/40 hover:text-crimson focus-ring" aria-label="Remove objective">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="add()" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                        <x-ui.icon name="plus" class="h-4 w-4" /> Add objective
                    </button>
                </div>

                {{-- Rich description --}}
                <x-ui.rich-editor name="description" label="Full description" :value="old('description', $course->description)"
                                  placeholder="Describe the course in detail…" />

                <div class="flex items-center justify-end gap-3 border-t border-line pt-5">
                    <span x-show="dirty" x-cloak class="text-sm text-ink/50">Unsaved changes</span>
                    <x-ui.button type="submit">Save settings</x-ui.button>
                </div>
            </fieldset>
        </form>
    </div>
</div>
