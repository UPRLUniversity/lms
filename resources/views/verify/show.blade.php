@php
    /** @var 'valid'|'revoked'|'not_found' $status */
    /** @var ?\App\Models\Certificate $certificate */
    /** @var string $serial */

    $theme = match ($status) {
        'valid' => ['icon' => 'check-circle', 'bg' => 'bg-success/10', 'text' => 'text-success', 'ring' => 'ring-success/20'],
        'revoked' => ['icon' => 'shield', 'bg' => 'bg-gold/15', 'text' => 'text-gold-ink', 'ring' => 'ring-gold/20'],
        default => ['icon' => 'x', 'bg' => 'bg-ink/5', 'text' => 'text-ink/50', 'ring' => 'ring-ink/10'],
    };
@endphp

<x-public-layout title="Verify {{ $serial }}" description="Certificate verification result for serial {{ $serial }}.">
    <div class="mx-auto max-w-md px-6 py-16 text-center">
        <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full {{ $theme['bg'] }} {{ $theme['text'] }} ring-8 {{ $theme['ring'] }}">
            <x-ui.icon :name="$theme['icon']" class="h-10 w-10" stroke-width="2" />
        </span>

        @if ($status === 'valid')
            <h1 class="mt-6 font-display text-2xl font-semibold text-ink">Certificate verified</h1>
            <p class="mt-2 text-ink/70">This is a genuine {{ config('brand.short') }} certificate of completion.</p>

            <dl class="mt-8 space-y-4 rounded-2xl border border-line bg-card p-6 text-left shadow-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink/50">Awarded to</dt>
                    <dd class="mt-1 font-display text-lg font-semibold text-ink">{{ $certificate->user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink/50">Course</dt>
                    <dd class="mt-1 text-ink">{{ $certificate->course->title }}</dd>
                </div>
                <div class="flex gap-8">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-ink/50">Issued</dt>
                        <dd class="mt-1 text-ink">{{ $certificate->issued_at->isoFormat('D MMMM YYYY') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-ink/50">Serial</dt>
                        <dd class="mt-1 font-mono text-ink">{{ $certificate->serial }}</dd>
                    </div>
                </div>
            </dl>

            <p class="mt-4 text-xs text-ink/40">
                The grade achieved is not shown here for the graduate's privacy — this page only confirms the
                certificate's authenticity.
            </p>
        @elseif ($status === 'revoked')
            <h1 class="mt-6 font-display text-2xl font-semibold text-ink">Certificate revoked</h1>
            <p class="mt-2 text-ink/70">
                Serial <span class="font-mono">{{ $certificate->serial }}</span> was issued but has since been
                revoked and is no longer valid.
            </p>
            <p class="mt-4 text-xs text-ink/40">Revoked {{ $certificate->revoked_at->isoFormat('D MMMM YYYY') }}.</p>
        @else
            <h1 class="mt-6 font-display text-2xl font-semibold text-ink">No certificate found</h1>
            <p class="mt-2 text-ink/70">
                We couldn't find a certificate with that serial. Double-check the number printed on the
                certificate and try again.
            </p>
        @endif

        <div class="mt-8">
            <x-ui.button variant="secondary" :href="route('verify.index')">Verify another certificate</x-ui.button>
        </div>
    </div>
</x-public-layout>
