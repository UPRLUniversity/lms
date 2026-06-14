@php
    use Illuminate\Support\Str;
@endphp

<x-public-layout title="Course catalogue" description="Browse published courses from the {{ config('brand.university') }}.">
    {{-- Hero strip --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
        <x-brand.sunburst class="pointer-events-none absolute -right-20 -top-24 h-96 w-96 text-white/10" />
        <div class="relative mx-auto max-w-7xl px-6 py-14 lg:px-8 lg:py-20">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wide text-white/90">
                {{ config('brand.short') }} Course Catalogue
            </span>
            <h1 class="mt-5 max-w-2xl font-display text-4xl font-bold leading-[1.1] sm:text-5xl">
                Find a course worth your time.
            </h1>
            <p class="mt-4 max-w-xl text-lg text-white/85">
                Public-relations, leadership and professional development courses taught by {{ config('brand.short') }} faculty.
            </p>
        </div>
        <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-surface to-transparent"></div>
    </section>

    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
        {{-- Filters (progressive: a plain GET form; no JS required) --}}
        <form method="GET" action="{{ route('catalogue.index') }}"
              class="grid grid-cols-1 gap-3 rounded-2xl border border-line bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4"
              x-data="{ faculty: @js($filters['faculty']) }">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="q" class="sr-only">Search courses</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                        <x-ui.icon name="search" class="h-5 w-5" />
                    </span>
                    <x-ui.input id="q" name="q" type="search" value="{{ $filters['search'] }}"
                                placeholder="Search title or code…" class="pl-10" />
                </div>
            </div>

            <div>
                <label for="faculty" class="sr-only">Faculty</label>
                <select id="faculty" name="faculty" x-model="faculty"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All faculties</option>
                    @foreach ($faculties as $f)
                        <option value="{{ $f->slug }}" @selected($filters['faculty'] === $f->slug)>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="department" class="sr-only">Department</label>
                <select id="department" name="department"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All departments</option>
                    @foreach ($faculties as $f)
                        <optgroup label="{{ $f->name }}" x-show="faculty === '' || faculty === @js($f->slug)">
                            @foreach ($f->departments as $d)
                                <option value="{{ $d->slug }}" @selected($filters['department'] === $d->slug)>{{ $d->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <div class="flex-1">
                    <label for="level" class="sr-only">Level</label>
                    <select id="level" name="level"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="">All levels</option>
                        @foreach ($levels as $l)
                            <option value="{{ $l->value }}" @selected($filters['level'] === $l->value)>{{ $l->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.button type="submit">Filter</x-ui.button>
            </div>
        </form>

        {{-- Results --}}
        <div class="mt-6 flex items-center justify-between">
            <p class="text-sm text-ink/60" aria-live="polite">
                {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}
                @if ($filters['search'] !== '' || $filters['faculty'] !== '' || $filters['department'] !== '' || $filters['level'] !== '')
                    <span class="text-ink/40">· filtered</span>
                    <a href="{{ route('catalogue.index') }}" class="ml-1 text-crimson hover:underline">Clear</a>
                @endif
            </p>
        </div>

        @if ($courses->isEmpty())
            <div class="mt-6">
                <x-ui.empty-state
                    icon="book"
                    title="No courses match your search"
                    description="Try a broader search or clear the filters to see everything on offer." />
            </div>
        @else
            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <x-courses.catalogue-card :course="$course" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $courses->links('pagination.uprl') }}
            </div>
        @endif
    </div>
</x-public-layout>
