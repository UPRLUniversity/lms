@php
    use App\Support\Learning\CurriculumItem;

    /** @var \App\Models\Course $course */
    /** @var \App\Support\Learning\CourseProgress $snapshot */
    /** @var \Illuminate\Support\Collection<int, CurriculumItem> $outstanding */

    $percent = $snapshot->percent();

    $href = fn (CurriculumItem $item) => match ($item->kind) {
        'lesson' => route('learn.show', [$course, $item->model]),
        'assessment' => route('assessments.start', [$course, $item->model]),
        'assignment' => route('assignments.show', [$course, $item->model]),
    };

    $kindIcon = fn (CurriculumItem $item) => match ($item->kind) {
        'lesson' => 'book',
        'assessment' => 'clipboard',
        'assignment' => 'document-text',
    };

    // A short, honest reason each item is still open — falls back to a sensible default
    // per kind when there's no attempt/submission yet to report on.
    $reason = fn (CurriculumItem $item) => $item->statusLabel ?? match ($item->kind) {
        'lesson' => 'Not completed yet',
        'assessment' => 'Not started yet',
        'assignment' => 'Not submitted yet',
    };

    // "Awaiting grading" and "exhausted attempts" aren't things the learner can act on
    // right now — soften the call-to-action copy accordingly instead of a bare "Go".
    $cta = fn (CurriculumItem $item) => match (true) {
        $item->statusTone === 'gold' && str_contains((string) $item->statusLabel, 'Awaiting grading') => 'View',
        str_contains((string) $item->statusLabel, 'no attempts left') => 'View history',
        default => match ($item->kind) {
            'lesson' => 'Go to lesson',
            'assessment' => 'Take assessment',
            'assignment' => 'View assignment',
        },
    };
@endphp

<x-learn-layout :title="'Almost there · '.$course->title">
    <main class="relative flex min-h-screen flex-col items-center overflow-hidden px-4 py-16">
        <x-brand.sunburst class="pointer-events-none absolute -right-32 -top-32 h-[34rem] w-[34rem] text-crimson/5" />

        <div class="relative mx-auto w-full max-w-2xl text-center">
            <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-crimson/10 text-crimson ring-8 ring-crimson/5">
                <x-ui.icon name="flag" class="h-10 w-10" />
            </span>

            <p class="mt-8 text-sm font-semibold uppercase tracking-[0.2em] text-crimson">{{ $percent }}% complete</p>
            <h1 class="mt-3 font-display text-4xl font-bold leading-tight text-ink sm:text-5xl">
                Almost there
            </h1>
            <p class="mx-auto mt-4 max-w-md text-lg leading-relaxed text-ink/70">
                Every lesson in <span class="font-semibold text-ink">{{ $course->title }}</span> is done —
                {{ $outstanding->count() === 1 ? 'one more thing' : $outstanding->count().' more things' }}
                and it's yours.
            </p>

            {{-- The checklist --}}
            <ul class="mx-auto mt-10 max-w-lg space-y-2 text-left">
                @foreach ($outstanding as $item)
                    <li class="flex items-center gap-3 rounded-2xl border border-line bg-card p-4 shadow-sm">
                        <span @class([
                            'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                            'bg-crimson/10 text-crimson' => $item->statusTone === 'crimson',
                            'bg-gold/15 text-gold-ink' => $item->statusTone === 'gold',
                            'bg-ink/5 text-ink/50' => ! $item->statusTone,
                        ])>
                            <x-ui.icon :name="$kindIcon($item)" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-ink">{{ $item->title() }}</span>
                            <span @class([
                                'block text-xs',
                                'text-crimson' => $item->statusTone === 'crimson',
                                'text-gold-ink' => $item->statusTone === 'gold',
                                'text-ink/50' => ! $item->statusTone,
                            ])>{{ $reason($item) }}</span>
                        </span>
                        <x-ui.button size="sm" variant="secondary" :href="$href($item)" class="shrink-0">
                            {{ $cta($item) }}
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <x-ui.button variant="ghost" :href="route('learning.index')">Back to My Learning</x-ui.button>
            </div>
        </div>
    </main>
</x-learn-layout>
