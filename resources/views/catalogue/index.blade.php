<x-public-layout title="Course catalogue" description="Browse published courses from the {{ config('brand.university') }}.">
    {{-- Hero strip --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
        <x-brand.sunburst class="pointer-events-none absolute -right-20 -top-24 h-96 w-96 text-white/10" />
        <div class="relative mx-auto max-w-7xl px-6 py-14 lg:px-8 lg:py-20">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wide text-white/90">
                {{ config('brand.short') }} Course Catalogue
            </span>
            <h1 class="mt-5 max-w-2xl font-display text-4xl font-bold leading-[1.1] text-white sm:text-5xl">
                Find a course worth your time.
            </h1>
            <p class="mt-4 max-w-xl text-lg text-white/85">
                Public-relations, leadership and professional development courses taught by {{ config('brand.short') }} faculty.
            </p>
        </div>
        <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-surface to-transparent"></div>
    </section>

    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8"
         x-data="dataTable('{{ route('catalogue.index') }}', { params: { q: '', faculty: '', department: '', level: '', sort: 'newest' } })">

        {{-- Filters — live (fetch + swap, no full reload). The GET form is the no-JS fallback. --}}
        <form method="GET" action="{{ route('catalogue.index') }}"
              class="grid grid-cols-1 gap-3 rounded-2xl border border-line bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5"
              @submit.prevent="filter()">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="q" class="sr-only">Search courses</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                        <x-ui.icon name="search" class="h-5 w-5" />
                    </span>
                    <x-ui.input id="q" name="q" type="search" value="{{ $filters['search'] }}"
                                placeholder="Search title or code…" class="pl-10"
                                x-model="params.q" @input.debounce.350ms="filter()" aria-controls="catalogue-results" />
                </div>
            </div>

            <div>
                <label for="faculty" class="sr-only">Faculty</label>
                <select id="faculty" name="faculty" x-model="params.faculty" @change="params.department = ''; filter()"
                        aria-controls="catalogue-results"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All faculties</option>
                    @foreach ($faculties as $f)
                        <option value="{{ $f->slug }}" @selected($filters['faculty'] === $f->slug)>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="department" class="sr-only">Department</label>
                <select id="department" name="department" x-model="params.department" @change="filter()"
                        aria-controls="catalogue-results"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All departments</option>
                    @foreach ($faculties as $f)
                        <optgroup label="{{ $f->name }}" x-show="params.faculty === '' || params.faculty === @js($f->slug)">
                            @foreach ($f->departments as $d)
                                <option value="{{ $d->slug }}" @selected($filters['department'] === $d->slug)>{{ $d->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="level" class="sr-only">Level</label>
                <select id="level" name="level" x-model="params.level" @change="filter()"
                        aria-controls="catalogue-results"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All levels</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l->value }}" @selected($filters['level'] === $l->value)>{{ $l->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <div class="flex-1">
                    <label for="sort" class="sr-only">Sort</label>
                    <select id="sort" name="sort" x-model="params.sort" @change="filter()"
                            aria-controls="catalogue-results"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest</option>
                        <option value="title" @selected($filters['sort'] === 'title')>Title A–Z</option>
                    </select>
                </div>
                <noscript><button type="submit" class="rounded-xl border border-line bg-card px-4 py-2.5 text-sm">Go</button></noscript>
                <x-ui.button type="button" variant="ghost" x-show="isFiltered" x-cloak @click="clearFilters()">Clear</x-ui.button>
            </div>
        </form>

        {{-- Loading hint --}}
        <div class="mt-4 flex items-center gap-2 text-sm text-ink/50" x-show="loading" x-cloak>
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
            </svg>
            Updating…
        </div>

        {{-- Results — swapped in place by the dataTable component. --}}
        <div id="catalogue-results" x-ref="results" class="mt-2"
             @click="onNav($event)"
             :class="loading && 'pointer-events-none opacity-60 transition-opacity'"
             :aria-busy="loading.toString()">
            @include('catalogue._grid')
        </div>
    </div>
</x-public-layout>
