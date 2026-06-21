@props([
    'rows' => 6,
    'cols' => 4,
    'avatar' => true,
])

{{--
    A generic table/list loading placeholder, styled to match the live data tables
    (admin People/Invitations, course Roster) and any future list. Decorative; the
    results region it overlays carries aria-busy. Reuse anywhere a list is fetched:
        <x-ui.skeleton-table :rows="8" :cols="5" />
--}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-line bg-card shadow-sm']) }}
     aria-hidden="true">
    {{-- Header strip --}}
    <div class="border-b border-line px-5 py-4">
        <x-ui.skeleton class="h-3 w-28" />
    </div>

    {{-- Rows --}}
    <div class="divide-y divide-line">
        @for ($r = 0; $r < (int) $rows; $r++)
            <div class="flex items-center gap-4 px-5 py-4">
                @if ($avatar)
                    <x-ui.skeleton class="h-9 w-9 shrink-0 rounded-full" />
                @endif
                <div class="min-w-0 flex-1 space-y-2">
                    <x-ui.skeleton class="h-3 w-1/3" />
                    <x-ui.skeleton class="h-2.5 w-1/4" />
                </div>
                @for ($c = 0; $c < max(0, (int) $cols - 2); $c++)
                    <x-ui.skeleton class="hidden h-3 w-16 sm:block" />
                @endfor
            </div>
        @endfor
    </div>
</div>
