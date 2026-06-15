<x-guest-layout>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink">Set your password</h1>
        <p class="mt-1 text-sm text-ink/70">
            Welcome, {{ $invitation->name }}. You've been invited to join
            {{ config('brand.short') }} as a <strong>{{ $invitation->role->label() }}</strong>.
            Choose a password to activate your account.
        </p>
    </div>

    <form method="POST" action="{{ route('invitations.accept.store', $invitation) }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full bg-surface" type="email"
                          :value="$invitation->email" disabled readonly />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-ui.password id="password" name="password" class="mt-1" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-ui.password id="password_confirmation" name="password_confirmation" class="mt-1" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                {{ __('Activate my account') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
