<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('brand.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">

            {{-- Form column --}}
            <main class="flex min-h-screen flex-col justify-center px-6 py-12 sm:px-12 lg:min-h-0">
                <div class="mx-auto w-full max-w-md">
                    <a href="{{ url('/') }}" class="inline-flex rounded-lg focus-ring" aria-label="{{ config('brand.name') }} home">
                        <x-brand.logo variant="color" alt="" class="h-28 w-auto sm:h-32" />
                    </a>

                    <div class="mt-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            {{-- Brand panel --}}
            <aside class="relative hidden overflow-hidden bg-gradient-to-br from-crimson to-crimson-dark lg:flex lg:flex-col lg:justify-between lg:p-12">
                <x-brand.sunburst class="pointer-events-none absolute -right-16 -top-16 h-80 w-80 text-white/10" />

                <div class="relative">
                    <x-brand.logo variant="white" class="h-28 w-auto" />
                </div>

                <div class="relative max-w-md">
                    <h2 class="font-display text-3xl font-semibold leading-tight text-white">
                        {{ config('brand.university') }}
                    </h2>
                    <p class="mt-4 text-lg text-white/80">
                        {{ config('brand.motto') }}
                    </p>
                </div>

                <p class="relative text-sm text-white/60">
                    &copy; {{ date('Y') }} {{ config('brand.short') }}. A place to learn, grow and lead.
                </p>
            </aside>
        </div>
    </body>
</html>
