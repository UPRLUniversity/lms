@props(['programme'])

@php
    use App\Support\Money;

    /** @var \App\Models\Programme $programme */
    $cover = $programme->coverUrl();
    $parts = $programme->relationLoaded('parts') ? $programme->parts : collect();
    // Set by PublicSiteService::programmes(); absent when a caller passes a bare model,
    // in which case the line is simply not claimed rather than counted lazily.
    $courseCount = $programme->getAttribute('catalogue_courses_count');
    $perPaper = (float) $programme->per_paper_fee;
@endphp

<a href="{{ route('programmes.show', $programme) }}"
   {{ $attributes->merge(['class' => 'group flex flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus-ring']) }}>

    {{-- Crest band. A cover image when the programme has one, the brand gradient plus
         the sunburst motif when it does not — never an empty grey box. --}}
    <div class="relative aspect-[16/7] overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark">
        @if ($cover)
            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <x-brand.sunburst class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 text-white/10" />
        @endif
        <span class="absolute bottom-3 left-4 font-display text-2xl font-bold tracking-tight text-white">
            {{ $programme->code }}
        </span>
    </div>

    <div class="flex flex-1 flex-col p-5">
        <h3 class="font-display text-lg font-semibold leading-snug text-ink group-hover:text-crimson">
            {{ $programme->name }}
        </h3>

        @if ($programme->tagline)
            <p class="mt-2 text-sm leading-relaxed text-ink/70">{{ $programme->tagline }}</p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-ink/65">
            @if ($courseCount !== null)
                <span class="inline-flex items-center gap-1.5">
                    <x-ui.icon name="book" class="h-4 w-4" />
                    {{ $courseCount }} {{ Str::plural('course', $courseCount) }}
                </span>
            @endif
            @if ($parts->isNotEmpty())
                <span class="inline-flex items-center gap-1.5">
                    <x-ui.icon name="layers" class="h-4 w-4" />
                    {{ $parts->count() }} {{ Str::plural('part', $parts->count()) }}
                </span>
            @endif
        </div>

        <div class="mt-auto flex items-baseline justify-between gap-3 pt-5">
            <span class="text-sm font-semibold text-ink">
                @if ($perPaper > 0)
                    {{ Money::format($perPaper) }}<span class="text-xs font-normal text-ink/65"> / paper</span>
                @else
                    <span class="text-success">Free to study</span>
                @endif
            </span>
            <span class="inline-flex items-center gap-1 text-sm font-medium text-crimson">
                Explore
                <x-ui.icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
            </span>
        </div>
    </div>
</a>
