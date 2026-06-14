<x-app-layout title="New course">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('courses.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to courses
            </a>
            <h2 class="mt-3 font-display text-2xl font-semibold text-ink">Create a course</h2>
            <p class="mt-1 text-ink/70">Start with the essentials — you'll add the cover, description, modules and lessons next.</p>
        </div>

        <x-ui.card>
            <form method="POST" action="{{ route('courses.store') }}" class="space-y-5">
                @csrf

                <x-ui.field name="title" label="Course title" :value="old('title')" required
                            placeholder="e.g. Foundations of Public Relations" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field name="code" label="Course code" :value="old('code')" required
                                hint="A short unique code, e.g. PRL101." placeholder="PRL101" />

                    <x-ui.field name="level" label="Level" required>
                        <select id="level" name="level"
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach ($levels as $level)
                                <option value="{{ $level->value }}" @selected(old('level') === $level->value)>{{ $level->label() }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>

                <x-ui.field name="department_id" label="Department" required>
                    <select id="department_id" name="department_id"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="">Select a department…</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) old('department_id') === $department->id)>
                                {{ $department->faculty?->name }} — {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($departments->isEmpty())
                    <p class="rounded-xl bg-gold/10 px-4 py-3 text-sm text-ink/70">
                        No departments exist yet. An admin needs to add a faculty and department first.
                    </p>
                @endif

                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-ui.button variant="ghost" :href="route('courses.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create &amp; continue</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
