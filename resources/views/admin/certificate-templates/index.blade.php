@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Certificate templates">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Certificate templates</h2>
                <p class="mt-1 text-ink/70">Design and signatories for issued certificates. Exactly one template is the system default.</p>
            </div>
            <x-ui.button :href="route('admin.certificate-templates.create')">
                <x-ui.icon name="plus" class="h-5 w-5" /> New template
            </x-ui.button>
        </div>

        @if ($templates->isEmpty())
            <x-ui.empty-state
                icon="certificate"
                title="No certificate templates yet"
                description="Create the first template — it becomes the system default automatically.">
                <x-slot name="action">
                    <x-ui.button :href="route('admin.certificate-templates.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> Add a template
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach ($templates as $template)
                    <x-ui.card :padding="false">
                        <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-display text-lg font-semibold text-ink">{{ $template->name }}</h3>
                                    @if ($template->is_default)
                                        <x-ui.badge variant="gold">System default</x-ui.badge>
                                    @endif
                                    <x-ui.badge variant="neutral">{{ $template->layout->label() }}</x-ui.badge>
                                    @if ($template->showGrade())
                                        <x-ui.badge variant="success">Shows grade</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-ink/65">
                                    {{ $template->courses_count }} {{ Str::plural('course', $template->courses_count) }} using it
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui.button size="sm" variant="ghost" :href="route('admin.certificate-templates.preview', $template)" target="_blank" rel="noopener">
                                    <x-ui.icon name="eye" class="h-4 w-4" /> Preview
                                </x-ui.button>
                                <x-ui.button size="sm" variant="ghost" :href="route('admin.certificate-templates.edit', $template)">
                                    <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                </x-ui.button>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
