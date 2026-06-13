<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('code') · {{ config('brand.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased">
        <main class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 py-12 text-center">
            <x-brand.sunburst class="pointer-events-none absolute -top-24 left-1/2 h-[28rem] w-[28rem] -translate-x-1/2 text-crimson/5" />

            <div class="relative max-w-md">
                <p class="font-display text-7xl font-semibold text-crimson sm:text-8xl">@yield('code')</p>

                <h1 class="mt-4 font-display text-2xl font-semibold text-ink">@yield('title')</h1>

                <p class="mt-2 text-ink/70">@yield('message')</p>

                <div class="mt-8 flex items-center justify-center gap-3">
                    <x-ui.button href="{{ url('/') }}">Back to safety</x-ui.button>
                    @auth
                        <x-ui.button variant="secondary" href="{{ route('dashboard') }}">Go to dashboard</x-ui.button>
                    @endauth
                </div>
            </div>

            <p class="relative mt-12 text-xs text-ink/40">{{ config('brand.short') }} — {{ config('brand.motto') }}</p>
        </main>
    </body>
</html>
