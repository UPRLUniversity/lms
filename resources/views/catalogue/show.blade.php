@php
    use Illuminate\Support\Str;

    $fmt = function (int $minutes): ?string {
        if ($minutes <= 0) return null;
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? $h.'h'.($m ? ' '.$m.'m' : '') : $m.'m';
    };

    $cover = $course->coverUrl();
    $lead = $course->leadInstructor();
    $totalLessons = $course->modules->sum(fn ($m) => $m->lessons->count());
    $totalMinutes = $course->modules->sum(fn ($m) => $m->lessons->sum('duration_minutes'));
@endphp

<x-public-layout :title="$course->title" :description="$course->summary">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
        <x-brand.sunburst class="pointer-events-none absolute -right-24 -top-28 h-[28rem] w-[28rem] text-white/10" />
        <div class="relative mx-auto grid max-w-7xl gap-10 px-6 py-12 lg:grid-cols-3 lg:px-8 lg:py-16">
            <div class="lg:col-span-2">
                <nav class="flex flex-wrap items-center gap-2 text-sm text-white/70" aria-label="Breadcrumb">
                    <a href="{{ route('catalogue.index') }}" class="hover:text-white focus-ring rounded">Catalogue</a>
                    @if ($course->department)
                        <span aria-hidden="true">/</span>
                        <span>{{ $course->department->faculty?->name }}</span>
                        <span aria-hidden="true">/</span>
                        <span class="text-white/90">{{ $course->department->name }}</span>
                    @endif
                </nav>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide">{{ $course->code }}</span>
                    <span class="rounded-full bg-gold/90 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-ink">{{ $course->level->label() }}</span>
                </div>

                <h1 class="mt-4 max-w-2xl font-display text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">{{ $course->title }}</h1>

                @if ($course->summary)
                    <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/85">{{ $course->summary }}</p>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/85">
                    @if ($lead)
                        <span class="inline-flex items-center gap-2">
                            <x-ui.avatar :user="$lead" size="xs" /> {{ $lead->name }}
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1.5"><x-ui.icon name="book" class="h-4 w-4" /> {{ $totalLessons }} {{ Str::plural('lesson', $totalLessons) }}</span>
                    @if ($d = $fmt($totalMinutes))
                        <span class="inline-flex items-center gap-1.5"><x-ui.icon name="clock" class="h-4 w-4" /> {{ $d }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-surface to-transparent"></div>
    </section>

    <div class="mx-auto max-w-7xl px-6 pb-4 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-3">
            {{-- Main column --}}
            <div class="lg:col-span-2 lg:py-10">
                {{-- Learning objectives --}}
                @if (! empty($course->learning_objectives))
                    <section aria-labelledby="objectives-heading" class="rounded-2xl border border-line bg-card p-6 shadow-sm lg:-mt-16">
                        <h2 id="objectives-heading" class="font-display text-xl font-semibold text-ink">What you'll learn</h2>
                        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($course->learning_objectives as $objective)
                                <li class="flex gap-2.5">
                                    <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
                                        <x-ui.icon name="check" class="h-3.5 w-3.5" stroke-width="2.5" />
                                    </span>
                                    <span class="text-sm leading-relaxed text-ink/80">{{ $objective }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Description --}}
                @if ($course->description)
                    <section aria-labelledby="about-heading" class="mt-10">
                        <h2 id="about-heading" class="font-display text-2xl font-semibold text-ink">About this course</h2>
                        <x-ui.prose :html="$course->description" class="mt-4" />
                    </section>
                @endif

                {{-- Curriculum --}}
                <section aria-labelledby="curriculum-heading" class="mt-10">
                    <div class="flex items-end justify-between gap-3">
                        <h2 id="curriculum-heading" class="font-display text-2xl font-semibold text-ink">Curriculum</h2>
                        <p class="text-sm text-ink/60">{{ $course->modules->count() }} {{ Str::plural('module', $course->modules->count()) }} · {{ $totalLessons }} {{ Str::plural('lesson', $totalLessons) }}</p>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach ($course->modules as $module)
                            <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="overflow-hidden rounded-xl border border-line bg-card">
                                <button type="button" @click="open = !open"
                                        class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left focus-ring"
                                        :aria-expanded="open.toString()">
                                    <span class="min-w-0">
                                        <span class="font-display font-semibold text-ink">{{ $module->title }}</span>
                                        <span class="ml-2 text-sm text-ink/50">{{ $module->lessons->count() }} {{ Str::plural('lesson', $module->lessons->count()) }}</span>
                                    </span>
                                    <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 text-ink/40 transition-transform" ::class="open && 'rotate-90'" />
                                </button>

                                <div x-show="open" x-collapse>
                                    <ul class="divide-y divide-line border-t border-line">
                                        @foreach ($module->lessons as $lesson)
                                            @php $embed = $lesson->videoEmbedUrl(); @endphp
                                            <li x-data="{ playing: false }">
                                                <div class="flex items-center gap-3 px-5 py-3">
                                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ink/5 text-ink/60">
                                                        <x-ui.icon :name="$lesson->type->icon()" class="h-4 w-4" />
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm text-ink">{{ $lesson->title }}</span>
                                                    </span>
                                                    @if ($lesson->is_free_preview)
                                                        @if ($embed || ($lesson->type->isText() && $lesson->content_text))
                                                            <button type="button" @click="playing = !playing"
                                                                    class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-0.5 text-xs font-medium text-success focus-ring">
                                                                <x-ui.icon name="play" class="h-3.5 w-3.5" /> <span x-text="playing ? 'Hide' : 'Preview'">Preview</span>
                                                            </button>
                                                        @else
                                                            <x-ui.badge variant="success">Free preview</x-ui.badge>
                                                        @endif
                                                    @endif
                                                    @if ($dm = $fmt((int) $lesson->duration_minutes))
                                                        <span class="text-xs text-ink/40">{{ $dm }}</span>
                                                    @endif
                                                </div>

                                                {{-- Inline free-preview player --}}
                                                @if ($lesson->is_free_preview && ($embed || ($lesson->type->isText() && $lesson->content_text)))
                                                    <div x-show="playing" x-collapse class="px-5 pb-5">
                                                        @if ($embed)
                                                            <div class="aspect-video overflow-hidden rounded-xl border border-line bg-ink/5">
                                                                <iframe src="{{ $embed }}" title="{{ $lesson->title }}" class="h-full w-full"
                                                                        loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                                            </div>
                                                        @else
                                                            <x-ui.prose :html="$lesson->content_text" class="rounded-xl border border-line bg-surface/60 p-4" />
                                                        @endif
                                                    </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Instructors --}}
                @if ($course->instructors->isNotEmpty())
                    <section aria-labelledby="instructors-heading" class="mt-10">
                        <h2 id="instructors-heading" class="font-display text-2xl font-semibold text-ink">Your instructors</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($course->instructors as $instructor)
                                <div class="flex gap-4 rounded-xl border border-line bg-card p-5 shadow-sm">
                                    <x-ui.avatar :user="$instructor" size="lg" />
                                    <div class="min-w-0">
                                        <p class="font-display font-semibold text-ink">{{ $instructor->name }}</p>
                                        @if ($instructor->pivot->is_lead)
                                            <span class="text-xs font-medium text-crimson">Lead instructor</span>
                                        @endif
                                        @if ($instructor->title)
                                            <p class="text-sm text-ink/60">{{ $instructor->title }}</p>
                                        @endif
                                        @if ($instructor->bio)
                                            <p class="mt-2 text-sm leading-relaxed text-ink/70 line-clamp-3">{{ $instructor->bio }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- Sticky enrol card --}}
            <aside class="lg:py-10">
                <div class="lg:sticky lg:top-24">
                    <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm lg:-mt-32">
                        <div class="aspect-[16/9] bg-gradient-to-br from-crimson to-crimson-dark">
                            @if ($cover)
                                <img src="{{ $cover }}" alt="" class="h-full w-full object-cover">
                            @else
                                <div class="relative h-full w-full">
                                    <x-brand.sunburst class="pointer-events-none absolute -right-6 -top-6 h-40 w-40 text-white/10" />
                                    <span class="absolute bottom-3 left-4 font-display text-2xl font-bold text-white/90">{{ $course->code }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="p-6">
                            <dl class="space-y-2.5 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink/60">Level</dt>
                                    <dd class="font-medium text-ink">{{ $course->level->label() }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-ink/60">Lessons</dt>
                                    <dd class="font-medium text-ink">{{ $totalLessons }}</dd>
                                </div>
                                @if ($d = $fmt($totalMinutes))
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink/60">Duration</dt>
                                        <dd class="font-medium text-ink">{{ $d }}</dd>
                                    </div>
                                @endif
                                @if ($course->department)
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="text-ink/60">Department</dt>
                                        <dd class="text-right font-medium text-ink">{{ $course->department->name }}</dd>
                                    </div>
                                @endif
                            </dl>

                            <div class="mt-6">
                                @auth
                                    <x-ui.button class="w-full" :href="route('dashboard')">Go to your dashboard</x-ui.button>
                                    <p class="mt-2 text-center text-xs text-ink/50">Enrolment opens soon.</p>
                                @else
                                    <x-ui.button class="w-full" :href="route('register')">Create an account to enrol</x-ui.button>
                                    <p class="mt-2 text-center text-xs text-ink/50">
                                        Already a member? <a href="{{ route('login') }}" class="text-crimson hover:underline">Log in</a>
                                    </p>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-public-layout>
