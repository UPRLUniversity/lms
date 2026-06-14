@props(['course'])

@php
    $cover = $course->coverUrl();
    $lead = $course->leadInstructor();
    $minutes = $course->total_duration ?? $course->totalDurationMinutes();
    $hours = intdiv($minutes, 60);
    $duration = $minutes > 0
        ? ($hours > 0 ? $hours.'h'.($minutes % 60 ? ' '.($minutes % 60).'m' : '') : $minutes.'m')
        : null;
@endphp

<a href="{{ route('catalogue.show', $course) }}"
   class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-ring">
    {{-- Cover --}}
    <div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark">
        @if ($cover)
            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <x-brand.sunburst class="pointer-events-none absolute -right-6 -top-6 h-40 w-40 text-white/10" />
            <span class="absolute bottom-3 left-4 font-display text-2xl font-bold text-white/90">{{ $course->code }}</span>
        @endif
        <span class="absolute left-3 top-3">
            <x-ui.badge variant="gold">{{ $course->level->label() }}</x-ui.badge>
        </span>
    </div>

    {{-- Body --}}
    <div class="flex flex-1 flex-col p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-crimson">{{ $course->code }}</p>
        <h3 class="mt-1 font-display text-lg font-semibold leading-snug text-ink line-clamp-2">{{ $course->title }}</h3>

        @if ($course->summary)
            <p class="mt-2 text-sm leading-relaxed text-ink/70 line-clamp-2">{{ $course->summary }}</p>
        @endif

        @if ($course->department)
            <p class="mt-3 text-xs text-ink/50">{{ $course->department->name }}</p>
        @endif

        <div class="mt-auto flex items-center justify-between gap-3 pt-4">
            @if ($lead)
                <div class="flex items-center gap-2">
                    <x-ui.avatar :user="$lead" size="xs" />
                    <span class="text-xs text-ink/70">{{ $lead->name }}</span>
                </div>
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-3 text-xs text-ink/50">
                <span class="inline-flex items-center gap-1">
                    <x-ui.icon name="book" class="h-4 w-4" />
                    {{ $course->lessons_count ?? $course->lessons()->count() }}
                </span>
                @if ($duration)
                    <span class="inline-flex items-center gap-1">
                        <x-ui.icon name="clock" class="h-4 w-4" /> {{ $duration }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</a>
