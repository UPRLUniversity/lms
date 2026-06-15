@props([
    'taken' => 0,
    'capacity' => null,
    'waitlist' => 0,
])

@php
    $taken = (int) $taken;
    $waitlist = (int) $waitlist;
    $hasLimit = $capacity !== null;
    $capacity = $hasLimit ? (int) $capacity : null;
    $pct = $hasLimit && $capacity > 0 ? min(100, (int) round($taken / $capacity * 100)) : 0;
    $full = $hasLimit && $taken >= $capacity;
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    <div class="flex items-baseline justify-between gap-2">
        <span class="text-sm font-medium text-ink/70">{{ $hasLimit ? 'Places filled' : 'Enrolment' }}</span>
        @if ($hasLimit)
            <span class="font-display text-sm font-semibold {{ $full ? 'text-crimson' : 'text-ink' }}">
                {{ $taken }}<span class="text-ink/40"> / {{ $capacity }}</span>
            </span>
        @else
            <span class="font-display text-sm font-semibold text-ink">{{ $taken }} enrolled</span>
        @endif
    </div>

    @if ($hasLimit)
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-ink/5"
             role="progressbar" aria-valuenow="{{ $taken }}" aria-valuemin="0" aria-valuemax="{{ $capacity }}"
             aria-label="Places filled">
            <div class="h-full rounded-full transition-all {{ $full ? 'bg-crimson' : 'bg-success' }}"
                 style="width: {{ $pct }}%"></div>
        </div>
        <p class="mt-1.5 text-xs text-ink/60">
            @if ($full)
                Course full
                @if ($waitlist > 0)
                    · {{ $waitlist }} on the waitlist
                @endif
            @else
                {{ max(0, $capacity - $taken) }} {{ \Illuminate\Support\Str::plural('place', max(0, $capacity - $taken)) }} left
            @endif
        </p>
    @else
        <p class="mt-1.5 text-xs text-ink/60">Unlimited places.</p>
    @endif
</div>
