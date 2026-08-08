@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Support\Collection $announcements */
@endphp

<x-app-layout :title="'Announcements · '.$course->title">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('learn.resume', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
            </a>
            <div class="mt-3 flex items-center gap-3">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                    <x-ui.icon name="megaphone" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">Announcements</h2>
                    <p class="text-sm text-ink/65">{{ $course->title }} · {{ $course->code }}</p>
                </div>
            </div>
        </div>

        @forelse ($announcements as $announcement)
            <article class="relative overflow-hidden rounded-2xl border border-line bg-card p-6 shadow-sm">
                {{-- A slim crimson spine marks each post. --}}
                <span class="absolute inset-y-0 left-0 w-1 bg-crimson/70" aria-hidden="true"></span>

                <div class="flex items-start justify-between gap-4">
                    <h3 class="font-display text-lg font-semibold leading-snug text-ink">{{ $announcement->title }}</h3>
                    <time class="mt-1 shrink-0 text-xs text-ink/65" datetime="{{ $announcement->created_at->toIso8601String() }}">
                        {{ $announcement->created_at->diffForHumans() }}
                    </time>
                </div>

                <div class="mt-2 flex items-center gap-2">
                    <x-ui.avatar :user="$announcement->author" size="xs" />
                    <span class="text-xs text-ink/65">{{ $announcement->author?->name ?? 'Instructor' }}</span>
                </div>

                <x-ui.prose class="mt-4" :html="$announcement->body" />
            </article>
        @empty
            <x-ui.empty-state icon="megaphone" title="No announcements yet"
                description="When your instructor posts an update for this course, it will appear here — and in your notifications." />
        @endforelse
    </div>
</x-app-layout>
