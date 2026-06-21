@php
    /** @var \App\Models\Course $course */
    /** @var \App\Support\Learning\CourseProgress $snapshot */
    /** @var \App\Models\Enrollment|null $enrollment */

    $fmtTime = function (int $seconds): string {
        if ($seconds < 60) return $seconds.'s';
        $m = intdiv($seconds, 60);
        $h = intdiv($m, 60);
        $m %= 60;
        return $h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m';
    };
@endphp

<x-learn-layout :title="'Course complete · '.$course->title">
    <main class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-16 text-center">
        {{-- Sunburst motif --}}
        <x-brand.sunburst class="pointer-events-none absolute -right-32 -top-32 h-[34rem] w-[34rem] text-gold/10" />
        <x-brand.sunburst class="pointer-events-none absolute -bottom-40 -left-40 h-[34rem] w-[34rem] text-crimson/5" />

        <div class="relative mx-auto w-full max-w-xl">
            <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-gold/15 text-gold ring-8 ring-gold/5">
                <x-ui.icon name="sparkles" class="h-10 w-10" />
            </span>

            <p class="mt-8 text-sm font-semibold uppercase tracking-[0.2em] text-crimson">Course complete</p>
            <h1 class="mt-3 font-display text-4xl font-bold leading-tight text-ink sm:text-5xl">
                Congratulations!
            </h1>
            <p class="mx-auto mt-4 max-w-md text-lg leading-relaxed text-ink/70">
                You've completed <span class="font-semibold text-ink">{{ $course->title }}</span>.
                Take a moment — you earned it.
            </p>

            {{-- Summary stats --}}
            <dl class="mx-auto mt-10 grid max-w-md grid-cols-3 gap-3">
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <dt class="text-xs font-medium text-ink/50">Lessons</dt>
                    <dd class="mt-1 font-display text-2xl font-semibold text-ink">{{ $snapshot->total() }}</dd>
                </div>
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <dt class="text-xs font-medium text-ink/50">Time spent</dt>
                    <dd class="mt-1 font-display text-2xl font-semibold text-ink">{{ $fmtTime($snapshot->totalSecondsSpent()) }}</dd>
                </div>
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <dt class="text-xs font-medium text-ink/50">Completed</dt>
                    <dd class="mt-1 font-display text-lg font-semibold text-ink">
                        {{ $enrollment?->completed_at?->isoFormat('D MMM') ?? 'Today' }}
                    </dd>
                </div>
            </dl>

            {{-- Certificate placeholder (Section 7 replaces this) --}}
            <div class="mx-auto mt-8 max-w-md rounded-2xl border border-dashed border-gold/40 bg-gold/5 p-5">
                <div class="flex items-center justify-center gap-2 text-gold">
                    <x-ui.icon name="certificate" class="h-5 w-5" />
                    <span class="font-display font-semibold">Certificate coming soon</span>
                </div>
                <p class="mt-1.5 text-sm text-ink/60">Your certificate of completion will appear here.</p>
            </div>

            {{-- Actions --}}
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <x-ui.button :href="route('learning.index')">Back to My Learning</x-ui.button>
                <x-ui.button variant="secondary" :href="route('learn.resume', $course)">Revisit the course</x-ui.button>
            </div>
        </div>
    </main>
</x-learn-layout>
