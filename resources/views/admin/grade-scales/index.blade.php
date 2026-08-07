@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Grade scales">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Grade scales</h2>
                <p class="mt-1 text-ink/70">Letter/point layers over the scoring engine. Exactly one scale is the system default at a time.</p>
            </div>
            <x-ui.button :href="route('admin.grade-scales.create')">
                <x-ui.icon name="plus" class="h-5 w-5" /> New scale
            </x-ui.button>
        </div>

        @if ($scales->isEmpty())
            <x-ui.empty-state
                icon="list"
                title="No grade scales yet"
                description="Create the first scale — it becomes the system default automatically.">
                <x-slot name="action">
                    <x-ui.button :href="route('admin.grade-scales.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> Add a scale
                    </x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                @foreach ($scales as $scale)
                    <x-ui.card :padding="false">
                        <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-display text-lg font-semibold text-ink">{{ $scale->name }}</h3>
                                    @if ($scale->is_default)
                                        <x-ui.badge variant="gold">System default</x-ui.badge>
                                    @endif
                                    <x-ui.badge :variant="$scale->status->badge()">{{ $scale->status->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-1 text-xs text-ink/50">
                                    {{ $scale->bands->count() }} {{ Str::plural('band', $scale->bands->count()) }} ·
                                    limit {{ rtrim(rtrim(number_format((float) $scale->scale_limit, 2), '0'), '.') }} ·
                                    @if ($scale->passMark() !== null)
                                        pass mark {{ $scale->passMark() }}% ·
                                    @endif
                                    {{ $scale->courses_count }} {{ Str::plural('course', $scale->courses_count) }} using it
                                </p>
                                @if ($scale->bands->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($scale->bands as $band)
                                            <x-ui.badge :variant="$band->color">
                                                {{ $band->label }} {{ $band->min_percent }}–{{ $band->max_percent }}%
                                                @unless ($band->is_pass)
                                                    <span class="text-crimson" title="This band is a fail">·&nbsp;fail</span>
                                                @endunless
                                            </x-ui.badge>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui.button size="sm" variant="ghost" :href="route('admin.grade-scales.edit', $scale)">
                                    <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                </x-ui.button>
                                @if ($scale->isArchived())
                                    <form method="POST" action="{{ route('admin.grade-scales.restore', $scale) }}">
                                        @csrf
                                        <x-ui.button size="sm" variant="secondary" type="submit">Restore</x-ui.button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.grade-scales.archive', $scale) }}"
                                          onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Archive this scale?', text: 'Courses already using it keep working; it just stops appearing for new selections.', confirmText: 'Archive' }).then(ok => ok && this.submit());">
                                        @csrf
                                        <x-ui.button size="sm" variant="ghost" type="submit" class="text-crimson">Archive</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
