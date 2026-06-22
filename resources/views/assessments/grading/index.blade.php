<x-app-layout title="Grading queue">
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Grading queue</h2>
            <p class="mt-1 text-sm text-ink/60">Attempts with written answers awaiting your grade.</p>
        </div>

        @if ($attempts->isEmpty())
            <x-ui.empty-state icon="check-circle" title="All caught up"
                description="There are no submitted attempts waiting to be graded." />
        @else
            <x-ui.card :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($attempts as $attempt)
                        <li class="flex items-center justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-ink">{{ $attempt->assessment->title }}</p>
                                <p class="text-sm text-ink/55">
                                    {{ $attempt->user->name }} · {{ $attempt->assessment->course->title }}
                                    · submitted {{ $attempt->submitted_at?->diffForHumans() }}
                                </p>
                            </div>
                            <x-ui.button size="sm" :href="route('grading.show', $attempt)">
                                <x-ui.icon name="pencil" class="h-4 w-4" /> Grade
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <div>{{ $attempts->links('pagination.uprl') }}</div>
        @endif
    </div>
</x-app-layout>
