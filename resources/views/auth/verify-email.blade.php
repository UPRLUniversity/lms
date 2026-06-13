<x-guest-layout>
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-ink">Verify your email</h1>
        <p class="mt-1 text-sm text-ink/70">
            {{ __('Thanks for signing up! Please verify your email by clicking the link we just sent you. Didn\'t receive it? We\'ll gladly send another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="rounded-md text-sm text-ink/70 underline hover:text-crimson focus-ring">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
