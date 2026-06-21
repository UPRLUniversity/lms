<x-app-layout title="People">
    @php $canManage = auth()->user()->can('create', \App\Models\User::class); @endphp

    <div class="mx-auto max-w-7xl space-y-6"
         x-data="dataTable('{{ route('admin.users.index') }}')">

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">People</h2>
                <p class="mt-1 text-ink/70">Manage accounts, roles and access across {{ config('brand.short') }}.</p>
            </div>

            @if ($canManage)
                <div class="flex items-center gap-2">
                    <x-ui.button variant="secondary" :href="route('admin.invitations.index')">
                        <x-ui.icon name="mail" class="h-5 w-5" /> Invitations
                    </x-ui.button>
                    <x-ui.button :href="route('admin.users.create')">
                        <x-ui.icon name="plus" class="h-5 w-5" /> New user
                    </x-ui.button>
                </div>
            @endif
        </div>

        {{-- Filters — live, no full-page reload. The <form> GET is a no-JS fallback. --}}
        <form method="GET" action="{{ route('admin.users.index') }}"
              class="flex flex-wrap items-end gap-3"
              @submit.prevent="filter()">
            <div class="min-w-[16rem] flex-1">
                <label for="search" class="sr-only">Search by name or email</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                        <x-ui.icon name="search" class="h-5 w-5" />
                    </span>
                    <x-ui.input id="search" name="search" type="search" value="{{ $search }}"
                                placeholder="Search name or email…" class="pl-10"
                                x-model="params.search"
                                @input.debounce.350ms="filter()"
                                aria-controls="users-results" />
                </div>
            </div>

            <div>
                <label for="role" class="sr-only">Filter by role</label>
                <select id="role" name="role"
                        x-model="params.role" @change="filter()"
                        aria-controls="users-results"
                        class="rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All roles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->value }}" @selected($activeRole === $r->value)>{{ $r->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- These buttons are the no-JS fallback; Alpine handles the live path. --}}
            <noscript><button type="submit" class="rounded-xl border border-line bg-card px-4 py-2.5 text-sm">Filter</button></noscript>
            <x-ui.button type="button" variant="ghost" x-show="isFiltered" x-cloak @click="clearFilters()">Clear</x-ui.button>

            {{-- Subtle loading indicator --}}
            <span x-show="loading" x-cloak class="inline-flex items-center gap-2 text-sm text-ink/50">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                </svg>
                Updating…
            </span>
        </form>

        {{-- Results region — swapped in place by the dataTable component.
             Clicks on sort/pagination links and row-action submits are delegated
             here so they keep working after each innerHTML swap. A skeleton overlay
             covers the table while it fetches (the region carries aria-busy). --}}
        <div class="relative">
            <div x-show="loading" x-cloak class="absolute inset-0 z-10">
                <x-ui.skeleton-table :rows="8" :cols="5" />
            </div>
            <div id="users-results" x-ref="results"
                 @click="onNav($event)" @submit="onAction($event)"
                 :class="loading && 'pointer-events-none opacity-0'"
                 :aria-busy="loading.toString()">
                @include('admin.users._table')
            </div>
        </div>
    </div>
</x-app-layout>
