@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $changes */
@endphp

<x-app-layout :title="'Change history · '.$course->title">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('courses.curriculum', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to the builder
            </a>
            <div class="mt-3 flex items-center gap-3">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gold/10 text-gold-ink">
                    <x-ui.icon name="arrow-path" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">Change history</h2>
                    <p class="text-sm text-ink/65">{{ $course->title }} · {{ $course->code }}</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-ink/65">
                Every edit made to this course. Changes marked
                <x-ui.badge variant="gold">Affects students</x-ui.badge>
                were announced to everyone taking it.
            </p>
        </div>

        @forelse ($changes as $change)
            <article class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[0.95rem] leading-relaxed text-ink">{{ $change->summary }}</p>
                        @if ($change->note)
                            <p class="mt-2 text-sm italic text-ink/65">“{{ $change->note }}”</p>
                        @endif
                    </div>
                    <x-ui.badge :variant="$change->significance->badge()" class="shrink-0">
                        {{ $change->significance->label() }}
                    </x-ui.badge>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink/65">
                    @if ($change->author)
                        <span class="inline-flex items-center gap-1.5">
                            <x-ui.avatar :user="$change->author" size="xs" />
                            {{ $change->author->name }}
                        </span>
                    @endif
                    <time datetime="{{ $change->created_at->toIso8601String() }}">
                        {{ $change->created_at->format('j M Y, g:ia') }}
                    </time>
                </div>
            </article>
        @empty
            <x-ui.empty-state icon="arrow-path" title="No changes recorded yet"
                description="Once you start editing this course, every change is recorded here — so you can always see what moved, when, and who moved it." />
        @endforelse

        @if ($changes->hasPages())
            <div>{{ $changes->links() }}</div>
        @endif
    </div>
</x-app-layout>
