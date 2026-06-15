@php
    $user = auth()->user();
    $primaryRole = $user?->roles->first()?->name;
@endphp

<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-line bg-card/90 px-4 backdrop-blur sm:px-6">
    {{-- Mobile: open drawer --}}
    <button type="button"
            class="rounded-lg p-2 text-ink/70 hover:bg-ink/5 hover:text-ink focus-ring lg:hidden"
            x-on:click="sidebarOpen = true"
            aria-label="Open navigation">
        <x-ui.icon name="menu" />
    </button>

    {{-- Desktop: collapse toggle --}}
    <button type="button"
            class="hidden rounded-lg p-2 text-ink/70 hover:bg-ink/5 hover:text-ink focus-ring lg:inline-flex"
            x-on:click="collapsed = ! collapsed"
            :aria-expanded="(! collapsed).toString()"
            aria-label="Toggle sidebar width">
        <x-ui.icon name="chevron-left" x-show="! collapsed" />
        <x-ui.icon name="chevron-right" x-show="collapsed" x-cloak />
    </button>

    {{-- Page title --}}
    <h1 class="truncate font-display text-lg font-semibold text-ink sm:text-xl">
        {{ $title ?? config('brand.name') }}
    </h1>

    <div class="ml-auto flex items-center gap-1 sm:gap-2">
        {{-- Notification bell (placeholder) --}}
        <button type="button"
                class="relative rounded-lg p-2 text-ink/70 hover:bg-ink/5 hover:text-ink focus-ring"
                aria-label="Notifications">
            <x-ui.icon name="bell" />
            <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-crimson" aria-hidden="true"></span>
        </button>

        {{-- User menu --}}
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button type="button" class="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-ink/5 focus-ring" aria-label="Account menu">
                    <x-ui.avatar :user="$user" size="sm" />
                    <span class="hidden text-left sm:block">
                        <span class="block text-sm font-medium leading-tight text-ink">{{ $user?->name }}</span>
                        <span class="block text-xs leading-tight text-ink/70">{{ $user?->email }}</span>
                    </span>
                    @if ($primaryRole)
                        <span class="hidden sm:inline-flex"><x-ui.role-badge :role="$primaryRole" /></span>
                    @endif
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
