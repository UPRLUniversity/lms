<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · '.config('brand.name') : config('brand.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @include('layouts.partials.favicons')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-surface text-ink antialiased"
          x-data="{
              sidebarOpen: false,
              collapsed: JSON.parse(localStorage.getItem('uprl:sidebar-collapsed') ?? 'false'),
          }"
          x-init="$watch('collapsed', value => localStorage.setItem('uprl:sidebar-collapsed', JSON.stringify(value)))">

        {{-- Skip link for keyboard users --}}
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-crimson focus:px-4 focus:py-2 focus:text-white">
            Skip to content
        </a>

        @include('layouts.partials.sidebar')

        {{-- Mobile drawer backdrop --}}
        <div x-show="sidebarOpen"
             x-transition.opacity
             x-on:click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-ink/50 lg:hidden"
             x-cloak></div>

        <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-[4.75rem]' : 'lg:pl-64'">
            @include('layouts.partials.topbar')

            <main id="main-content" class="p-4 sm:p-6 lg:p-8">
                {{-- Breadcrumbs, where a page supplies them. Above the heading so the
                     trail reads before the title, as a breadcrumb should. --}}
                @isset($breadcrumbs)
                    <div class="mb-4">
                        {{ $breadcrumbs }}
                    </div>
                @endisset

                {{-- Legacy Breeze header slot (e.g. profile page) renders above content. --}}
                @isset($header)
                    <div class="mb-6">
                        {{ $header }}
                    </div>
                @endisset

                {{ $slot }}
            </main>

            {{-- App footer (Section 15). Deliberately quiet — this is a working
                 surface, not a marketing page — but the legal pages have to be
                 reachable from inside the app, not only from the public site. --}}
            <footer class="border-t border-line px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-3 text-xs text-ink/65 sm:flex-row">
                    <p>&copy; {{ date('Y') }} {{ config('brand.university') }}. {{ __('common.all_rights_reserved') }}</p>

                    <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1" aria-label="Legal and support">
                        <a href="{{ route('legal.terms') }}" class="rounded hover:text-ink focus-ring">{{ __('common.terms') }}</a>
                        <a href="{{ route('legal.privacy') }}" class="rounded hover:text-ink focus-ring">{{ __('common.privacy') }}</a>
                        @if ($support = config('mail.support'))
                            <a href="mailto:{{ $support }}" class="rounded hover:text-ink focus-ring">{{ __('common.support') }}</a>
                        @endif
                        <x-ui.locale-switcher />
                    </nav>
                </div>
            </footer>
        </div>

        {{-- Global top-right toast stack (server flashes + JS `toast` events). --}}
        <x-ui.toasts />
    </body>
</html>
