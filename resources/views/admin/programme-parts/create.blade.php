<x-app-layout title="New part">
    <div class="mx-auto max-w-xl space-y-6">
        <a href="{{ route('admin.programmes.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Programmes
        </a>
        <h2 class="font-display text-2xl font-semibold text-ink">Add a part</h2>

        <x-ui.card>
            @if ($programmes->isEmpty())
                <x-ui.empty-state
                    icon="layers"
                    title="No programmes yet"
                    description="A part belongs to a programme, so create the programme first.">
                    <x-slot name="action">
                        <x-ui.button :href="route('admin.programmes.create')">Create a programme</x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            @else
                <form method="POST" action="{{ route('admin.programme-parts.store') }}" class="space-y-5">
                    @csrf

                    <x-ui.field name="programme_id" label="Programme" required>
                        <select id="programme_id" name="programme_id" required
                                class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @foreach ($programmes as $programme)
                                <option value="{{ $programme->id }}"
                                        @selected(old('programme_id', $selected) == $programme->id)>
                                    {{ $programme->name }} ({{ $programme->code }})
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field name="name" label="Part name" required :value="old('name')"
                                placeholder="e.g. Part I" />

                    <x-ui.field name="credit_target" label="Stated credit total" type="number"
                                :value="old('credit_target')" min="0" step="1"
                                hint="The total the printed curriculum states for this part. Leave blank if it states none. Compulsory and required-elective credits are counted against it; pure electives and GNS are not." />

                    <x-ui.field name="description" label="Description" hint="Optional">
                        <textarea id="description" name="description" rows="2"
                                  class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">{{ old('description') }}</textarea>
                    </x-ui.field>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-ui.button variant="ghost" :href="route('admin.programmes.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit">Add part</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
