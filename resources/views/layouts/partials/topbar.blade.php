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
        {{-- Cart. Hidden when empty inside the app shell: a signed-in learner mid-lesson
             does not need a permanent shopping reminder, but must not lose a basket
             they have started. --}}
        @php $cartCount = app(\App\Services\Commerce\CartService::class)->itemCount(auth()->user()); @endphp
        @if ($cartCount > 0)
            <a href="{{ route('cart.index') }}"
               class="relative rounded-lg p-2 text-ink/70 hover:bg-ink/5 hover:text-ink focus-ring"
               aria-label="{{ $cartCount === 1 ? 'Cart, 1 item' : 'Cart, '.$cartCount.' items' }}">
                <x-ui.icon name="shopping-cart" class="h-5 w-5" />
                <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-crimson px-1 text-[10px] font-bold text-white">
                    {{ $cartCount > 9 ? '9+' : $cartCount }}
                </span>
            </a>
        @endif

        {{-- Notification bell --}}
        <div class="relative"
             x-data="notificationBell({ recentUrl: @js(route('notifications.recent')), markAllUrl: @js(route('notifications.mark-all-read')) })"
             @click.outside="open = false">
            <button type="button" @click="toggle()"
                    class="relative rounded-lg p-2 text-ink/70 hover:bg-ink/5 hover:text-ink focus-ring"
                    :aria-expanded="open.toString()"
                    aria-label="Notifications">
                <x-ui.icon name="bell" />
                <span x-show="unreadCount > 0" x-cloak
                      class="absolute right-1 top-1 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-crimson px-1 text-[10px] font-semibold leading-none text-white"
                      x-text="unreadCount > 9 ? '9+' : unreadCount"></span>
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] rounded-xl border border-line bg-card shadow-lg"
                 style="display: none;">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h2 class="text-sm font-semibold text-ink">Notifications</h2>
                    <button type="button" x-show="unreadCount > 0" @click="markAllRead()"
                            class="text-xs font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                        Mark all read
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <template x-if="loading">
                        <div class="space-y-2 p-4">
                            <x-ui.skeleton class="h-10" />
                            <x-ui.skeleton class="h-10" />
                            <x-ui.skeleton class="h-10" />
                        </div>
                    </template>

                    <template x-if="! loading && notifications.length === 0">
                        <p class="px-4 py-8 text-center text-sm text-ink/50">You're all caught up.</p>
                    </template>

                    <template x-for="n in notifications" :key="n.id">
                        <a :href="n.url"
                           class="relative flex gap-3 border-b border-line py-3 pl-4 pr-4 text-sm transition-colors last:border-0 hover:bg-surface focus-ring"
                           :class="! n.read && 'bg-crimson/[0.03]'">
                            {{-- Unread rail --}}
                            <span x-show="! n.read" class="absolute inset-y-0 left-0 w-0.5 bg-crimson" aria-hidden="true"></span>
                            {{-- Tinted type tile. The glyph is a bell here (Alpine can't swap the
                                 compiled SVG per row); the tint carries the notification's tone. --}}
                            <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="n.tile">
                                <x-ui.icon name="bell" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-ink" :class="! n.read ? 'font-semibold' : 'font-medium'" x-text="n.title"></span>
                                <span class="block truncate text-ink/60" x-text="n.body"></span>
                                <span class="mt-0.5 block text-[11px] font-medium uppercase tracking-wide text-ink/35" x-text="n.time"></span>
                            </span>
                        </a>
                    </template>
                </div>

                <a href="{{ route('notifications.index') }}"
                   class="block rounded-b-xl border-t border-line px-4 py-2.5 text-center text-sm font-medium text-crimson hover:bg-surface focus-ring">
                    View all notifications
                </a>
            </div>
        </div>

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
