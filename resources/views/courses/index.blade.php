@php
    use Illuminate\Support\Str;
@endphp

<x-app-layout title="Teaching">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">{{ $isAdmin ? 'All courses' : 'Your courses' }}</h2>
                <p class="mt-1 text-ink/70">
                    {{ $isAdmin ? 'Govern every course across '.config('brand.short').'.' : 'Author, refine and publish your courses.' }}
                </p>
            </div>

            @can('create', \App\Models\Course::class)
                <x-ui.button :href="route('courses.create')">
                    <x-ui.icon name="plus" class="h-5 w-5" /> New course
                </x-ui.button>
            @endcan
        </div>

        {{-- Status filter --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('courses.index') }}"
               @class([
                   'rounded-full px-3.5 py-1.5 text-sm font-medium focus-ring transition-colors',
                   'bg-crimson text-white' => $activeStatus === '',
                   'bg-card border border-line text-ink/70 hover:text-ink' => $activeStatus !== '',
               ])>All</a>
            @foreach ($statuses as $s)
                <a href="{{ route('courses.index', ['status' => $s->value]) }}"
                   @class([
                       'rounded-full px-3.5 py-1.5 text-sm font-medium focus-ring transition-colors',
                       'bg-crimson text-white' => $activeStatus === $s->value,
                       'bg-card border border-line text-ink/70 hover:text-ink' => $activeStatus !== $s->value,
                   ])>{{ $s->label() }}</a>
            @endforeach
        </div>

        @if ($courses->isEmpty())
            <x-ui.empty-state
                icon="book"
                title="{{ $activeStatus !== '' ? 'No courses with this status' : 'Create your first course' }}"
                description="{{ $activeStatus !== '' ? 'Try a different status filter.' : 'Build a course with modules, lessons and resources — then submit it for review.' }}">
                @if ($activeStatus === '')
                    <x-slot name="action">
                        @can('create', \App\Models\Course::class)
                            <x-ui.button :href="route('courses.create')">
                                <x-ui.icon name="plus" class="h-5 w-5" /> New course
                            </x-ui.button>
                        @endcan
                    </x-slot>
                @endif
            </x-ui.empty-state>
        @else
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    @php $cover = $course->coverUrl(); @endphp
                    <div class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition hover:shadow-md">
                        <a href="{{ route('courses.edit', $course) }}" class="relative block aspect-[16/9] overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark focus-ring">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="" class="h-full w-full object-cover">
                            @else
                                <x-brand.sunburst class="pointer-events-none absolute -right-6 -top-6 h-40 w-40 text-white/10" />
                                <span class="absolute bottom-3 left-4 font-display text-xl font-bold text-white/90">{{ $course->code }}</span>
                            @endif
                            <span class="absolute left-3 top-3">
                                <x-ui.badge :variant="$course->status->badge()">{{ $course->status->label() }}</x-ui.badge>
                            </span>
                        </a>

                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-xs font-medium uppercase tracking-wide text-crimson">{{ $course->code }}</p>
                            <h3 class="mt-1 font-display text-lg font-semibold leading-snug text-ink line-clamp-2">
                                <a href="{{ route('courses.edit', $course) }}" class="hover:text-crimson focus-ring rounded">{{ $course->title }}</a>
                            </h3>
                            <p class="mt-1 text-sm text-ink/50">{{ $course->department?->name ?? 'No department' }}</p>

                            <div class="mt-3 flex items-center gap-3 text-xs text-ink/50">
                                <span class="inline-flex items-center gap-1">
                                    <x-ui.icon name="book" class="h-4 w-4" /> {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}
                                </span>
                                <span>·</span>
                                <span>{{ $course->level->label() }}</span>
                            </div>

                            <div class="mt-auto flex items-center gap-2 pt-4">
                                <x-ui.button size="sm" variant="secondary" :href="route('courses.edit', $course)" class="flex-1">
                                    <x-ui.icon name="pencil" class="h-4 w-4" /> Build
                                </x-ui.button>
                                @if ($course->isPublished())
                                    <x-ui.button size="sm" variant="ghost" :href="route('catalogue.show', $course)">
                                        <x-ui.icon name="eye" class="h-4 w-4" /> View
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $courses->links('pagination.uprl') }}
            </div>
        @endif
    </div>
</x-app-layout>
