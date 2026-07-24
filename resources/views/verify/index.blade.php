{{-- The manual-entry front door — a QR scan skips straight to verify.show. --}}
<x-public-layout title="Verify a certificate" description="Confirm a UPRL certificate of completion is genuine.">
    <div class="mx-auto max-w-md px-6 py-16 text-center">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-crimson/10 text-crimson">
            <x-ui.icon name="certificate" class="h-8 w-8" />
        </span>

        <h1 class="mt-6 font-display text-2xl font-semibold text-ink">Verify a certificate</h1>
        <p class="mt-2 text-ink/70">
            Enter the serial number printed on a {{ config('brand.short') }} certificate
            (e.g. UPRL-{{ now()->year }}-A1B2C3) to confirm it's genuine.
        </p>

        <form method="POST" action="{{ route('verify.lookup') }}" class="mt-8 space-y-4 text-left">
            @csrf
            <x-ui.field name="serial" label="Certificate serial" required>
                <x-ui.input name="serial" id="serial" required autofocus
                    placeholder="UPRL-{{ now()->year }}-A1B2C3"
                    class="text-center font-mono uppercase tracking-widest" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full justify-center">Verify</x-ui.button>
        </form>
    </div>
</x-public-layout>
