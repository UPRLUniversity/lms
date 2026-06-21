@props([
    'title' => null,
])

{{--
    The focus shell for the learning player — its own chrome (no app sidebar), so the
    learner's whole screen is the course. Brand fonts + tokens, skip link, global
    toast stack and reduced-motion handling all carry over from the app.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' · '.config('brand.name') : config('brand.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased">
        <a href="#lesson-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-crimson focus:px-4 focus:py-2 focus:text-white">
            Skip to lesson
        </a>

        {{ $slot }}

        {{-- Global top-right toast stack (server flashes + JS `toast` events). --}}
        <x-ui.toasts />
    </body>
</html>
