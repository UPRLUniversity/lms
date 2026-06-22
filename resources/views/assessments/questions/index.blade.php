<x-app-layout :title="'Question bank — '.$course->title">
    <div class="mx-auto max-w-7xl space-y-6"
         x-data="dataTable('{{ route('questions.index', $course) }}', { params: { search: '{{ $filters['search'] }}', type: '{{ $filters['type'] }}', difficulty: '{{ $filters['difficulty'] }}', category: '{{ $filters['category'] }}' } })">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to {{ $course->title }}
                </a>
                <h2 class="mt-2 font-display text-2xl font-semibold text-ink">Question bank</h2>
                <p class="mt-1 text-sm text-ink/60">Author reusable questions, then build quizzes and exams from them.</p>
            </div>

            <div class="flex items-center gap-2" x-data="{ open: false }">
                <x-ui.button variant="secondary" :href="route('questions.import.form', $course)">
                    <x-ui.icon name="download" class="h-4 w-4" /> Import
                </x-ui.button>
                <div class="relative">
                    <x-ui.button @click="open = !open" aria-haspopup="true" ::aria-expanded="open">
                        <x-ui.icon name="plus" class="h-4 w-4" /> New question
                    </x-ui.button>
                    <div x-show="open" x-cloak @click.outside="open = false"
                         class="absolute right-0 z-20 mt-2 w-64 overflow-hidden rounded-xl border border-line bg-card py-1 shadow-lg">
                        @foreach ($types as $t)
                            <a href="{{ route('questions.create', ['course' => $course, 'type' => $t->value]) }}"
                               class="flex items-center gap-2.5 px-3 py-2 text-sm text-ink hover:bg-surface focus-ring">
                                <x-ui.icon :name="$t->icon()" class="h-4 w-4 text-ink/50" /> {{ $t->label() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('questions.index', $course) }}"
              class="flex flex-wrap items-end gap-3" @submit.prevent="filter()">
            <div class="min-w-[16rem] flex-1">
                <label for="search" class="sr-only">Search questions</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                        <x-ui.icon name="search" class="h-5 w-5" />
                    </span>
                    <x-ui.input id="search" name="search" type="search" placeholder="Search prompt text…" class="pl-10"
                                x-model="params.search" @input.debounce.350ms="filter()" aria-controls="bank-results" />
                </div>
            </div>

            <div>
                <label for="type" class="sr-only">Type</label>
                <select id="type" name="type" x-model="params.type" @change="filter()"
                        class="rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All types</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ $t->shortLabel() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="difficulty" class="sr-only">Difficulty</label>
                <select id="difficulty" name="difficulty" x-model="params.difficulty" @change="filter()"
                        class="rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All difficulty</option>
                    @foreach ($difficulties as $d)
                        <option value="{{ $d->value }}">{{ $d->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="category" class="sr-only">Category</label>
                <select id="category" name="category" x-model="params.category" @change="filter()"
                        class="rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-ui.button type="button" variant="ghost" x-show="isFiltered" x-cloak @click="clearFilters()">Clear</x-ui.button>
        </form>

        {{-- Results --}}
        <div class="relative">
            <div x-show="loading" x-cloak class="absolute inset-0 z-10">
                <x-ui.skeleton-table :rows="8" :cols="5" />
            </div>
            <div id="bank-results" x-ref="results"
                 @click="onNav($event)" @submit="onAction($event)"
                 :class="loading && 'pointer-events-none opacity-0'" :aria-busy="loading.toString()">
                @include('assessments.questions._table')
            </div>
        </div>
    </div>
</x-app-layout>
