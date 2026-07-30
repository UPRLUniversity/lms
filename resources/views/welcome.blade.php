@php
    /** @var array{courses: int, programmes: int, instructors: int, learners: int} $stats */
    /** @var \Illuminate\Database\Eloquent\Collection $programmes */
    /** @var \Illuminate\Database\Eloquent\Collection $featured */

    // Round the band down to a confident figure ("40+ courses") rather than printing a
    // precise 43 that changes every time somebody publishes. Small numbers stay exact —
    // "0+ learners" would be absurd.
    $band = [
        ['label' => 'Courses on offer', 'value' => $stats['courses'], 'icon' => 'book'],
        ['label' => 'Qualifications', 'value' => $stats['programmes'], 'icon' => 'layers'],
        ['label' => 'Expert instructors', 'value' => $stats['instructors'], 'icon' => 'users'],
        ['label' => 'Learners enrolled', 'value' => $stats['learners'], 'icon' => 'graduation'],
    ];

    $steps = [
        ['title' => 'Browse the catalogue', 'copy' => 'Filter by qualification, part, faculty or level until you find the paper you need.', 'icon' => 'search'],
        ['title' => 'Add it to your cart', 'copy' => 'No account needed yet. Fill your basket first — it will still be there when you sign in.', 'icon' => 'shopping-cart'],
        ['title' => 'Pay securely', 'copy' => 'Card or bank transfer, in naira. Programme entry fees are charged once, never per paper.', 'icon' => 'credit-card'],
        ['title' => 'Learn at your pace', 'copy' => 'Lessons, quizzes and assignments with your progress saved on every device.', 'icon' => 'play'],
        ['title' => 'Get certified', 'copy' => 'Finish the course and earn a certificate anyone can verify by serial or QR code.', 'icon' => 'certificate'],
    ];

    $values = [
        ['title' => 'Creativity', 'icon' => 'sparkles', 'copy' => 'Courses and tools that spark fresh thinking and original work.'],
        ['title' => 'Competence', 'icon' => 'check-circle', 'copy' => 'Structured learning and real assessment that builds genuine skill.'],
        ['title' => 'Character', 'icon' => 'shield', 'copy' => 'A community grounded in integrity, leadership and service.'],
    ];
@endphp

<x-public-layout
    :description="'Study for your CPR, DPR and Professional Variant qualifications at the '.config('brand.university').'. '.config('brand.motto').'.'">

    {{-- ───────────────────────── 1 · Hero ───────────────────────── --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
        <x-brand.sunburst class="pointer-events-none absolute -right-24 -top-28 h-[30rem] w-[30rem] text-white/10 motion-safe:animate-[spin_140s_linear_infinite]" />
        <div class="pointer-events-none absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-white/5 blur-2xl"></div>

        <div class="relative mx-auto max-w-7xl px-6 pb-24 pt-14 sm:pb-28 sm:pt-20 lg:px-8">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wide text-white/90">
                    {{ config('brand.university') }}
                </span>

                <h1 class="mt-6 font-display text-4xl font-bold leading-[1.1] text-white sm:text-5xl lg:text-6xl">
                    Learn with purpose.<br>Lead with character.
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/85">
                    Study for your professional public-relations qualification online — taught by
                    {{ config('brand.short') }} faculty, examined to the Institute's standards, and built around
                    <span class="font-semibold text-white">{{ strtolower(config('brand.motto')) }}</span>.
                </p>

                {{-- Search + programme picker. A plain GET form so it deep-links straight
                     into the catalogue's own filters and works with JavaScript off. --}}
                <form method="GET" action="{{ route('catalogue.index') }}"
                      class="mt-8 rounded-2xl border border-white/20 bg-white/10 p-3 backdrop-blur-sm sm:flex sm:items-center sm:gap-3">
                    <div class="relative flex-1">
                        <label for="hero-q" class="sr-only">Search courses</label>
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                            <x-ui.icon name="search" class="h-5 w-5" />
                        </span>
                        <input id="hero-q" type="search" name="q" placeholder="Search a course or code…"
                               class="block w-full rounded-xl border-0 bg-white py-2.5 pl-10 pr-3 text-ink placeholder:text-ink/45 focus-ring-inverse">
                    </div>

                    @if ($programmes->isNotEmpty())
                        <div class="mt-3 sm:mt-0 sm:w-56">
                            <label for="hero-programme" class="sr-only">Qualification</label>
                            <select id="hero-programme" name="programme"
                                    class="block w-full rounded-xl border-0 bg-white py-2.5 text-ink focus-ring-inverse">
                                <option value="">Any qualification</option>
                                @foreach ($programmes as $programme)
                                    <option value="{{ $programme->slug }}">{{ $programme->code }} — {{ $programme->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit"
                            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-ink px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-ink/90 focus-ring-inverse sm:mt-0 sm:w-auto">
                        Find a course
                    </button>
                </form>

                <div class="mt-8 flex flex-wrap items-center gap-x-4 gap-y-3">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3 text-base font-semibold text-crimson shadow-lg transition hover:bg-white/90 focus-ring-inverse">
                            Continue learning
                        </a>
                        <a href="{{ route('catalogue.index') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-white/40 px-6 py-3 text-base font-semibold text-white transition hover:bg-white/10 focus-ring-inverse">
                            Browse the catalogue
                        </a>
                    @else
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center justify-center rounded-xl bg-white px-6 py-3 text-base font-semibold text-crimson shadow-lg transition hover:bg-white/90 focus-ring-inverse">
                            Create your account
                        </a>
                        <a href="{{ route('programmes.index') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-white/40 px-6 py-3 text-base font-semibold text-white transition hover:bg-white/10 focus-ring-inverse">
                            See the qualifications
                        </a>
                    @endauth
                </div>
            </div>
        </div>

        <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-surface to-transparent"></div>
    </section>

    {{-- ──────────────────── 2 · Stats band ────────────────────
         Lifted over the hero, so it needs z-10 or the relatively-positioned hero
         paints across the card tops. --}}
    <section class="relative z-10 mx-auto -mt-12 max-w-7xl px-6 lg:px-8" aria-label="{{ config('brand.short') }} at a glance">
        <div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
            @foreach ($band as $stat)
                <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                        <x-ui.icon :name="$stat['icon']" class="h-5 w-5" />
                    </span>
                    <p class="mt-3 font-display text-3xl font-bold text-ink">
                        {{ $stat['value'] >= 10 ? (intdiv($stat['value'], 10) * 10).'+' : $stat['value'] }}
                    </p>
                    <p class="mt-0.5 text-sm text-ink/65">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ──────────────────── 3 · Programmes grid ──────────────────── --}}
    <section class="mx-auto max-w-7xl px-6 pt-20 lg:px-8" aria-labelledby="programmes-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <h2 id="programmes-heading" class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    Choose your qualification
                </h2>
                <p class="mt-3 text-lg text-ink/70">
                    Every paper on the platform belongs to a programme. Start where your career is, and work up.
                </p>
            </div>
            <a href="{{ route('programmes.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-crimson hover:text-crimson-dark focus-ring">
                All programmes <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        @if ($programmes->isEmpty())
            <div class="mt-8">
                <x-ui.empty-state
                    icon="layers"
                    title="Programmes are on their way"
                    description="Our qualification structure is being published. In the meantime, the full course catalogue is open.">
                    <x-slot name="action">
                        <x-ui.button :href="route('catalogue.index')">Browse courses</x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            </div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($programmes as $programme)
                    <x-programmes.card :programme="$programme" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- ──────────────────── 4 · Featured courses ──────────────────── --}}
    <section class="mx-auto max-w-7xl px-6 pt-20 lg:px-8" aria-labelledby="featured-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <h2 id="featured-heading" class="font-display text-3xl font-bold text-ink sm:text-4xl">
                    Popular right now
                </h2>
                <p class="mt-3 text-lg text-ink/70">
                    The papers {{ config('brand.short') }} learners are working through this term.
                </p>
            </div>
            <a href="{{ route('catalogue.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-crimson hover:text-crimson-dark focus-ring">
                Full catalogue <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        @if ($featured->isEmpty())
            <div class="mt-8">
                <x-ui.empty-state
                    icon="book"
                    title="No courses are published yet"
                    description="Courses appear here the moment they are published to the public catalogue." />
            </div>
        @else
            <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $course)
                    <x-courses.catalogue-card :course="$course" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- ──────────────────── 5 · Why UPRL ──────────────────── --}}
    <section class="mt-20 border-y border-line bg-card" aria-labelledby="values-heading">
        <div class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 id="values-heading" class="font-display text-3xl font-bold text-ink sm:text-4xl">Why {{ config('brand.short') }}</h2>
                <p class="mt-3 text-lg text-ink/70">
                    Three words have guided this institution since the day it opened.
                </p>
            </div>

            <div class="mt-14 grid gap-8 md:grid-cols-3">
                @foreach ($values as $value)
                    <div class="text-center">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-crimson/10 text-crimson">
                            <x-ui.icon :name="$value['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 font-display text-xl font-semibold text-ink">{{ $value['title'] }}</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-ink/70">{{ $value['copy'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ──────────────────── 6 · How enrolment works ──────────────────── --}}
    <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8" aria-labelledby="how-heading">
        <div class="mx-auto max-w-2xl text-center">
            <h2 id="how-heading" class="font-display text-3xl font-bold text-ink sm:text-4xl">How enrolment works</h2>
            <p class="mt-3 text-lg text-ink/70">Five steps from browsing to a verifiable certificate.</p>
        </div>

        <ol class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($steps as $i => $step)
                <li class="relative flex h-full flex-col rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-crimson text-white">
                        <x-ui.icon :name="$step['icon']" class="h-5 w-5" />
                    </span>
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-crimson">Step {{ $i + 1 }}</p>
                    <h3 class="mt-1 font-display text-base font-semibold leading-snug text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink/65">{{ $step['copy'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ──────────────────── 7 · Two closing calls to action ──────────────────── --}}
    <section class="mx-auto max-w-7xl px-6 pb-24 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-2">

            {{-- Teach with us --}}
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-crimson to-crimson-dark p-8 text-white shadow-lg sm:p-10">
                <x-brand.sunburst class="pointer-events-none absolute -right-12 -top-12 h-60 w-60 text-white/10" />
                <div class="relative">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wide">
                        Teach with us
                    </span>
                    <h2 class="mt-4 font-display text-2xl font-bold sm:text-3xl">Share what you know</h2>
                    <p class="mt-3 max-w-md text-white/85">
                        {{ config('brand.short') }} is always looking for practitioners to author and lead papers.
                        Tell us what you would like to teach and our academic office will be in touch.
                    </p>
                    <a href="{{ route('register') }}"
                       class="mt-6 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-crimson shadow transition hover:bg-white/90 focus-ring-inverse">
                        <x-ui.icon name="user-plus" class="h-5 w-5" /> Apply to teach
                    </a>
                </div>
            </div>

            {{-- Verify a certificate --}}
            <div class="relative overflow-hidden rounded-3xl border border-line bg-card p-8 shadow-sm sm:p-10">
                <x-brand.sunburst class="pointer-events-none absolute -right-12 -top-12 h-60 w-60 text-gold/10" />
                <div class="relative">
                    <span class="inline-flex items-center gap-2 rounded-full bg-gold/15 px-3 py-1 text-xs font-medium uppercase tracking-wide text-gold-ink">
                        Employers
                    </span>
                    <h2 class="mt-4 font-display text-2xl font-bold text-ink sm:text-3xl">Verify a certificate</h2>
                    <p class="mt-3 max-w-md text-ink/70">
                        Every {{ config('brand.short') }} certificate carries a serial and a QR code. Enter the serial
                        and we will confirm the holder, the course and the date it was awarded.
                    </p>
                    <x-ui.button size="lg" variant="secondary" class="mt-6" :href="route('verify.index')">
                        <x-ui.icon name="certificate" class="h-5 w-5" /> Check a serial
                    </x-ui.button>
                </div>
            </div>
        </div>
    </section>
</x-public-layout>
