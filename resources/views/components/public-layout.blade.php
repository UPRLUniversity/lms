@props([
    'title' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · '.config('brand.short') : config('brand.university') }}</title>
        <meta name="description" content="{{ $description ?? 'Explore courses from the '.config('brand.university').'. '.config('brand.motto').'.' }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-crimson focus:px-4 focus:py-2 focus:text-white">
            Skip to content
        </a>

        {{-- Public top bar --}}
        <header class="sticky top-0 z-30 border-b border-line bg-card/90 backdrop-blur">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3 lg:px-8" aria-label="Primary">
                <a href="{{ url('/') }}" class="inline-flex rounded-lg focus-ring" aria-label="{{ config('brand.name') }} home">
                    <x-brand.logo variant="color" alt="{{ config('brand.short') }}" class="h-14 w-auto sm:h-16" />
                </a>

                <div class="flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('catalogue.index') }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium focus-ring',
                           'text-crimson' => request()->routeIs('catalogue.*'),
                           'text-ink/70 hover:text-ink' => ! request()->routeIs('catalogue.*'),
                       ])>
                        Catalogue
                    </a>

                    @auth
                        <x-ui.button size="sm" :href="route('dashboard')">Dashboard</x-ui.button>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-ink/70 hover:text-ink focus-ring">Log in</a>
                        <x-ui.button size="sm" :href="route('register')">Get started</x-ui.button>
                    @endauth
                </div>
            </nav>
        </header>

        <main id="main-content">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-line bg-card">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 py-10 text-center sm:flex-row sm:text-left lg:px-8">
                <div class="flex items-center gap-3">
                    <x-brand.logo variant="color" alt="" class="h-9 w-auto" />
                    <span class="font-display text-sm italic text-gold">{{ config('brand.motto') }}</span>
                </div>
                <p class="text-xs text-ink/50">&copy; {{ date('Y') }} {{ config('brand.university') }}. All rights reserved.</p>
            </div>
        </footer>

        <x-ui.toasts />
    </body>
</html>
