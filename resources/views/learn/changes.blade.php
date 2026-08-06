@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $changes */
    /** @var \Illuminate\Support\Carbon|null $enrolledAt */
@endphp

<x-app-layout :title="'What’s changed · '.$course->title">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('learn.resume', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/50 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
            </a>
            <div class="mt-3 flex items-center gap-3">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-gold-ink">
                    <x-ui.icon name="arrow-path" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">What’s changed</h2>
                    <p class="text-sm text-ink/60">{{ $course->title }} · {{ $course->code }}</p>
                </div>
            </div>
            @if ($enrolledAt)
                <p class="mt-3 text-sm text-ink/55">
                    Updates your instructor has made since you joined on
                    <time datetime="{{ $enrolledAt->toIso8601String() }}">{{ $enrolledAt->format('j F Y') }}</time>.
                </p>
            @endif
        </div>

        @forelse ($changes as $change)
            <article class="relative overflow-hidden rounded-2xl border border-line bg-card p-6 shadow-sm">
                <span class="absolute inset-y-0 left-0 w-1 bg-gold/70" aria-hidden="true"></span>

                <div class="flex items-start justify-between gap-4">
                    <p class="text-[0.95rem] leading-relaxed text-ink">{{ $change->summary }}</p>
                    <time class="mt-0.5 shrink-0 text-xs text-ink/45" datetime="{{ $change->created_at->toIso8601String() }}">
                        {{ $change->created_at->diffForHumans() }}
                    </time>
                </div>

                @if ($change->note)
                    <div class="mt-4 rounded-xl border border-line bg-surface p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-ink/45">A note from your instructor</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink/80">{{ $change->note }}</p>
                    </div>
                @endif

                @if ($change->author)
                    <div class="mt-4 flex items-center gap-2">
                        <x-ui.avatar :user="$change->author" size="xs" />
                        <span class="text-xs text-ink/55">{{ $change->author->name }}</span>
                    </div>
                @endif
            </article>
        @empty
            <x-ui.empty-state icon="arrow-path" title="Nothing has changed"
                description="This course hasn’t been updated since you joined. If your instructor moves a deadline or adds something you need to do, you’ll find it here — and in your notifications." />
        @endforelse

        @if ($changes->hasPages())
            <div>{{ $changes->links() }}</div>
        @endif
    </div>
</x-app-layout>
