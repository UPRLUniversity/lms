<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('brand.university') }} · {{ config('brand.short') }} LMS</title>
        <meta name="description" content="The learning platform of the {{ config('brand.university') }}. {{ config('brand.motto') }}.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased">

        {{-- Top navigation (over the crimson hero) --}}
        <header class="absolute inset-x-0 top-0 z-20">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8" aria-label="Primary">
                <a href="{{ url('/') }}" class="inline-flex rounded-lg focus-ring" aria-label="{{ config('brand.name') }} home">
                    <x-brand.logo variant="white" alt="{{ config('brand.short') }}" class="h-16 w-auto sm:h-20" />
                </a>

                <div class="flex items-center gap-2 sm:gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-crimson shadow-sm transition hover:bg-white/90 focus-ring">
                            Go to dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-white/90 hover:text-white focus-ring">
                            Log in
                        </a>
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-crimson shadow-sm transition hover:bg-white/90 focus-ring">
                            Get started
                        </a>
                    @endauth
                </div>
            </nav>
        </header>

        <main>
            {{-- Hero --}}
            <section class="relative overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark text-white">
                <x-brand.sunburst class="pointer-events-none absolute -right-24 -top-24 h-[34rem] w-[34rem] text-white/10 motion-safe:animate-[spin_140s_linear_infinite]" />
                <div class="pointer-events-none absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-white/5 blur-2xl"></div>

                <div class="relative mx-auto max-w-7xl px-6 pb-24 pt-36 sm:pb-32 sm:pt-44 lg:px-8">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-wide text-white/90">
                            {{ config('brand.university') }}
                        </span>

                        <h1 class="mt-6 font-display text-4xl font-bold leading-[1.1] text-white sm:text-5xl lg:text-6xl">
                            Learn with purpose.<br>Lead with character.
                        </h1>

                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/85">
                            The official learning platform of the {{ config('brand.university') }}.
                            Enrol in courses, learn from expert instructors, and earn recognised
                            certificates — all built around our values of
                            <span class="font-semibold text-white">creativity, competence and character</span>.
                        </p>

                        <div class="mt-10 flex flex-wrap items-center gap-4">
                            @auth
                                <a href="{{ route('dashboard') }}"
                                   class="inline-flex items-center justify-center rounded-xl bg-white px-7 py-3.5 text-base font-semibold text-crimson shadow-lg transition hover:bg-white/90 focus-ring">
                                    Continue learning
                                </a>
                            @else
                                <a href="{{ route('register') }}"
                                   class="inline-flex items-center justify-center rounded-xl bg-white px-7 py-3.5 text-base font-semibold text-crimson shadow-lg transition hover:bg-white/90 focus-ring">
                                    Create your free account
                                </a>
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center justify-center rounded-xl border border-white/40 px-7 py-3.5 text-base font-semibold text-white transition hover:bg-white/10 focus-ring">
                                    I already have an account
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>

                {{-- Soft transition into the page --}}
                <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-surface to-transparent"></div>
            </section>

            {{-- Values (lifted above the hero — needs z-10 so the relatively
                 positioned hero doesn't paint over the overlapping card tops). --}}
            <section class="relative z-10 mx-auto -mt-12 max-w-7xl px-6 lg:px-8">
                <div class="grid gap-5 sm:grid-cols-3">
                    @php
                        $values = [
                            ['title' => 'Creativity', 'icon' => 'graduation', 'copy' => 'Courses and tools that spark fresh thinking and original work.'],
                            ['title' => 'Competence', 'icon' => 'check', 'copy' => 'Structured learning and real assessment that builds genuine skill.'],
                            ['title' => 'Character', 'icon' => 'shield', 'copy' => 'A community grounded in integrity, leadership and service.'],
                        ];
                    @endphp
                    @foreach ($values as $value)
                        <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                                <x-ui.icon :name="$value['icon']" class="h-6 w-6" />
                            </span>
                            <h2 class="mt-4 font-display text-xl font-semibold text-ink">{{ $value['title'] }}</h2>
                            <p class="mt-2 text-sm leading-relaxed text-ink/70">{{ $value['copy'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Features --}}
            <section class="mx-auto max-w-7xl px-6 py-24 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-display text-3xl font-bold text-ink sm:text-4xl">Everything you need to learn and teach</h2>
                    <p class="mt-4 text-lg text-ink/70">One platform for students, instructors and administrators across the university.</p>
                </div>

                <div class="mt-14 grid gap-8 md:grid-cols-3">
                    @php
                        $features = [
                            ['icon' => 'book', 'title' => 'Rich courses', 'copy' => 'Lessons, resources and assignments organised into clear, guided learning paths.'],
                            ['icon' => 'users', 'title' => 'Expert instructors', 'copy' => 'Learn directly from UPRL faculty, with feedback and grading built in.'],
                            ['icon' => 'certificate', 'title' => 'Verified certificates', 'copy' => 'Complete a course and earn a shareable, QR-verifiable certificate.'],
                        ];
                    @endphp
                    @foreach ($features as $feature)
                        <div class="flex gap-4">
                            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                                <x-ui.icon :name="$feature['icon']" class="h-6 w-6" />
                            </span>
                            <div>
                                <h3 class="font-display text-lg font-semibold text-ink">{{ $feature['title'] }}</h3>
                                <p class="mt-1.5 text-sm leading-relaxed text-ink/70">{{ $feature['copy'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Call to action --}}
            @guest
                <section class="mx-auto max-w-7xl px-6 pb-24 lg:px-8">
                    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-crimson to-crimson-dark px-8 py-16 text-center shadow-lg sm:px-16">
                        <x-brand.sunburst class="pointer-events-none absolute -left-16 -top-16 h-72 w-72 text-white/10" />
                        <div class="relative">
                            <h2 class="font-display text-3xl font-bold text-white sm:text-4xl">Ready to begin?</h2>
                            <p class="mx-auto mt-4 max-w-xl text-lg text-white/85">
                                Create your account in minutes and start your first course today.
                            </p>
                            <a href="{{ route('register') }}"
                               class="mt-8 inline-flex items-center justify-center rounded-xl bg-white px-8 py-3.5 text-base font-semibold text-crimson shadow-lg transition hover:bg-white/90 focus-ring">
                                Get started — it's free
                            </a>
                        </div>
                    </div>
                </section>
            @endguest
        </main>

        {{-- Footer --}}
        <footer class="border-t border-line bg-card">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 py-10 text-center sm:flex-row sm:text-left lg:px-8">
                <div class="flex items-center gap-3">
                    <x-brand.logo variant="color" alt="" class="h-10 w-auto" />
                    <span class="font-display text-sm italic text-gold">{{ config('brand.motto') }}</span>
                </div>
                <p class="text-xs text-ink/50">&copy; {{ date('Y') }} {{ config('brand.university') }}. All rights reserved.</p>
            </div>
        </footer>
    </body>
</html>
