@php
    $user = auth()->user();
    $primaryRole = $user->roles->first()?->name;
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ') ?: $user->name;
@endphp

<x-app-layout title="Dashboard">
    <div class="mx-auto max-w-7xl space-y-8">
        {{-- Greeting + role --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">Welcome back, {{ $firstName }}</h2>
                    @if ($primaryRole)
                        <x-ui.role-badge :role="$primaryRole" />
                    @endif
                </div>
                <p class="mt-1 text-ink/70">
                    @if ($isAdmin)
                        Here’s what’s happening across {{ config('brand.short') }}.
                    @elseif ($isStaff)
                        Here’s an overview of your teaching. Let’s keep building.
                    @elseif ($isAuditor)
                        A read-only overview of {{ config('brand.short') }}.
                    @else
                        Here’s an overview of your learning. Let’s keep the momentum going.
                    @endif
                </p>
            </div>

            @if ($isAdmin || $isAuditor)
                <x-ui.button size="sm" variant="secondary" :href="route('reports.index')">
                    <x-ui.icon name="chart" class="h-4 w-4" /> Report centre
                </x-ui.button>
            @endif
        </div>

        @if ($isAdmin || $isAuditor)
            @include('dashboard.admin')
        @elseif ($isStaff)
            @include('dashboard.instructor')
        @else
            @include('dashboard.student')
        @endif
    </div>
</x-app-layout>
