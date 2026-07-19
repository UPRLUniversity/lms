@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Rubrics">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Rubrics</h2>
                <p class="mt-1 text-sm text-ink/60">Reusable grading grids — attach one to any of your assignments.</p>
            </div>
            @if ($canCreate)
                <x-ui.button :href="route('rubrics.create')">
                    <x-ui.icon name="plus" class="h-5 w-5" /> New rubric
                </x-ui.button>
            @endif
        </div>

        @if ($rubrics->isEmpty())
            <x-ui.empty-state icon="list" title="No rubrics yet"
                description="A rubric turns grading into consistent, transparent choices: criteria down the side, achievement levels across the top, points behind every cell.">
                @if ($canCreate)
                    <x-slot name="action">
                        <x-ui.button :href="route('rubrics.create')">Build your first rubric</x-ui.button>
                    </x-slot>
                @endif
            </x-ui.empty-state>
        @else
            <x-ui.card :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($rubrics as $rubric)
                        <li>
                            <a href="{{ route('rubrics.edit', $rubric) }}"
                               class="flex items-center gap-3 px-5 py-4 transition hover:bg-surface/60 focus-ring">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                                    <x-ui.icon name="list" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-ink">{{ $rubric->name }}</span>
                                    <span class="text-sm text-ink/55">
                                        {{ $rubric->criteria->count() }} {{ Str::plural('criterion', $rubric->criteria->count()) }}
                                        · {{ rtrim(rtrim(number_format($rubric->totalPoints(), 2), '0'), '.') }} pts max
                                        · used by {{ $rubric->assignments_count }} {{ Str::plural('assignment', $rubric->assignments_count) }}
                                    </span>
                                </span>
                                <x-ui.icon name="chevron-right" class="h-5 w-5 text-ink/30" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <div>{{ $rubrics->links('pagination.uprl') }}</div>
        @endif
    </div>
</x-app-layout>
