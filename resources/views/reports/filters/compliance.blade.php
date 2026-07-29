@php
    $select = 'block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson';
    $selectedCourses = collect(request('course_ids', []))->map(fn ($v) => (string) $v);
@endphp

<div class="space-y-5" x-data="{ cohort: '{{ request('cohort', 'department') }}' }">
    {{-- Target course(s) --}}
    <div>
        <label class="block text-sm font-medium text-ink">Target course(s) <span class="text-crimson">*</span></label>
        <p class="text-xs text-ink/70">The course(s) the cohort is expected to complete.</p>
        <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-xl border border-line bg-card p-3">
            @foreach ($options['courses'] as $course)
                <label class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-surface">
                    <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                        @checked($selectedCourses->contains((string) $course->id))
                        class="rounded border-line text-crimson focus:ring-crimson">
                    <span class="text-sm text-ink">{{ $course->code }} — {{ $course->title }}</span>
                </label>
            @endforeach
        </div>
        @error('course_ids')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
    </div>

    {{-- Cohort selector --}}
    <div>
        <span class="block text-sm font-medium text-ink">Cohort</span>
        <div class="mt-2 flex flex-wrap gap-4">
            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="radio" name="cohort" value="department" x-model="cohort" class="border-line text-crimson focus:ring-crimson"> By department
            </label>
            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="radio" name="cohort" value="emails" x-model="cohort" class="border-line text-crimson focus:ring-crimson"> By e-mail list
            </label>
        </div>
    </div>

    <div x-show="cohort === 'department'" x-cloak>
        <x-ui.field name="department_id" label="Department" hint="Everyone enrolled in this department’s courses.">
            <select name="department_id" id="department_id" class="{{ $select }}">
                <option value="">Select a department…</option>
                @foreach ($options['departments'] as $department)
                    <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </x-ui.field>
    </div>

    <div x-show="cohort === 'emails'" x-cloak>
        <x-ui.field name="emails" label="E-mail list" hint="One address per line (or comma-separated). Unknown addresses are ignored.">
            <textarea name="emails" id="emails" rows="4" class="{{ $select }}" placeholder="ada@uprl.test&#10;bello@uprl.test">{{ request('emails') }}</textarea>
        </x-ui.field>
    </div>
</div>
