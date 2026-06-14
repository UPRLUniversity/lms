@php
    $user = auth()->user();
    $navItems = collect(config('navigation'))->filter(function ($item) use ($user) {
        $roles = $item['roles'] ?? [];

        // '*' means everyone; otherwise the user must hold one of the listed roles.
        // Students never match admin items; the auditor only matches what it's
        // granted (and reaches those screens read-only).
        return in_array('*', $roles, true) || (bool) $user?->hasAnyRole($roles);
    });
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-line bg-card transition-all duration-200"
    :class="{
        '-translate-x-full lg:translate-x-0': ! sidebarOpen,
        'translate-x-0': sidebarOpen,
        'lg:w-[4.75rem]': collapsed,
        'lg:w-64': ! collapsed,
    }"
    x-on:keydown.escape.window="sidebarOpen = false"
    aria-label="Sidebar">

    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center border-b border-line px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded-lg focus-ring" aria-label="{{ config('brand.name') }} — Dashboard">
            <span :class="collapsed ? 'lg:hidden' : ''"><x-brand.logo variant="color" alt="" class="h-12 w-auto" /></span>
            <span class="hidden" :class="collapsed ? 'lg:inline-flex' : 'lg:hidden'"><x-brand.logo variant="mark" alt="" class="h-9 w-9" /></span>
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
        @foreach ($navItems as $item)
            @php
                $isActive = $item['route'] && request()->routeIs($item['match']);
                $isPlaceholder = $item['route'] === null;
                $href = $item['route'] ? route($item['route']) : null;
            @endphp

            @if ($isPlaceholder)
                <span
                    class="group relative flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink/40"
                    :class="collapsed ? 'lg:justify-center' : ''"
                    title="{{ $item['label'] }} — coming soon"
                    aria-disabled="true">
                    <x-ui.icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                    <span :class="collapsed ? 'lg:sr-only' : ''">{{ $item['label'] }}</span>
                    <span class="ml-auto" :class="collapsed ? 'lg:hidden' : ''">
                        <x-ui.badge variant="neutral" class="text-[10px]">Soon</x-ui.badge>
                    </span>
                </span>
            @else
                <a href="{{ $href }}"
                    @class([
                        'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-ring',
                        'bg-crimson/10 text-crimson' => $isActive,
                        'text-ink/70 hover:bg-ink/5 hover:text-ink' => ! $isActive,
                    ])
                    :class="collapsed ? 'lg:justify-center' : ''"
                    title="{{ $item['label'] }}"
                    @if ($isActive) aria-current="page" @endif>
                    {{-- Active rail --}}
                    @if ($isActive)
                        <span class="absolute inset-y-1.5 left-0 w-1 rounded-r-full bg-crimson" aria-hidden="true"></span>
                    @endif
                    <x-ui.icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                    <span :class="collapsed ? 'lg:sr-only' : ''">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Footer / motto --}}
    <div class="shrink-0 border-t border-line px-4 py-3">
        <p class="text-xs text-ink/40" :class="collapsed ? 'lg:hidden' : ''">{{ config('brand.motto') }}</p>
    </div>
</aside>
