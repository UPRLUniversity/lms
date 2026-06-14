<x-guest-layout>
    <div class="mb-6">
        <x-brand.sunburst class="mb-6 h-16 w-16 text-crimson/20" />
        <h1 class="font-display text-2xl font-semibold text-ink">This invitation can't be used</h1>
        <p class="mt-2 text-sm text-ink/70">
            The link you followed has expired, has already been used, or isn't valid.
            Invitations are single-use and time-limited for your security.
        </p>
        <p class="mt-2 text-sm text-ink/70">
            Please ask an administrator to send you a fresh invitation.
        </p>
    </div>

    <x-ui.button :href="route('login')" class="w-full justify-center">Go to sign in</x-ui.button>
</x-guest-layout>
