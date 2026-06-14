<x-app-layout title="New faculty">
    <div class="mx-auto max-w-xl space-y-6">
        <a href="{{ route('admin.faculties.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Academic structure
        </a>
        <h2 class="font-display text-2xl font-semibold text-ink">Create a faculty</h2>

        <x-ui.card>
            <form method="POST" action="{{ route('admin.faculties.store') }}" class="space-y-5">
                @csrf
                <x-ui.field name="name" label="Faculty name" :value="old('name')" required
                            placeholder="e.g. Faculty of Communication &amp; Media Studies" />
                <x-ui.field name="description" label="Description" hint="Optional">
                    <textarea id="description" name="description" rows="3"
                              class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">{{ old('description') }}</textarea>
                </x-ui.field>
                <div class="flex justify-end gap-3 pt-2">
                    <x-ui.button variant="ghost" :href="route('admin.faculties.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create faculty</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
