<x-app-layout :title="$programme->name">
    <div class="mx-auto max-w-2xl space-y-6">
        <a href="{{ route('admin.programmes.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Programmes
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="font-display text-2xl font-semibold text-ink">{{ $programme->name }}</h2>
            <x-ui.badge variant="crimson">{{ $programme->code }}</x-ui.badge>
        </div>

        <x-ui.card>
            <form method="POST" action="{{ route('admin.programmes.update', $programme) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.programmes._form', ['programme' => $programme])

                <div class="flex justify-end gap-3 border-t border-line pt-5">
                    <x-ui.button variant="ghost" :href="route('admin.programmes.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
