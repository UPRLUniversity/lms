<x-public-layout title="Qualifications"
                 :description="'The professional qualifications awarded through the '.config('brand.university').' — certificate, diploma, professional variant and master class.'">

    {{-- Hero strip — same shape as the catalogue's, so the public site reads as one place. --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
        <x-brand.sunburst class="pointer-events-none absolute -right-20 -top-24 h-96 w-96 text-white/10" />
        <div class="relative mx-auto max-w-7xl px-6 py-14 lg:px-8 lg:py-20">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wide text-white/90">
                {{ config('brand.short') }} Qualifications
            </span>
            <h1 class="mt-5 max-w-2xl font-display text-4xl font-bold leading-[1.1] text-white sm:text-5xl">
                Start where you are. Qualify where you want to be.
            </h1>
            <p class="mt-4 max-w-xl text-lg text-white/85">
                Each programme is a ladder of parts, and each part lists the papers you sit for it.
                Pick the one that matches your experience.
            </p>
        </div>
        <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-surface to-transparent"></div>
    </section>

    <div class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
        @if ($programmes->isEmpty())
            <x-ui.empty-state
                icon="layers"
                title="No qualifications are published yet"
                description="Our programme structure is being prepared. The full course catalogue is already open to browse.">
                <x-slot name="action">
                    <x-ui.button :href="route('catalogue.index')">Browse courses</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($programmes as $programme)
                    <x-programmes.card :programme="$programme" />
                @endforeach
            </div>

            <p class="mt-10 text-center text-sm text-ink/65">
                Not sure which to choose?
                <a href="{{ route('catalogue.index') }}" class="rounded font-medium text-crimson hover:underline focus-ring">
                    Browse every course
                </a>
                and filter by qualification as you go.
            </p>
        @endif
    </div>
</x-public-layout>
