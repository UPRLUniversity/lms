<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-ink">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-ink">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="rounded-md text-sm text-ink/70 underline hover:text-crimson focus-ring">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-success">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="phone" :value="__('Phone')" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                              :value="old('phone', $user->phone)" autocomplete="tel" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>

            <div>
                <x-input-label for="title" :value="__('Title')" />
                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                              :value="old('title', $user->title)" placeholder="e.g. Senior Lecturer" />
                <x-input-error class="mt-2" :messages="$errors->get('title')" />
            </div>
        </div>

        <div>
            <x-input-label for="bio" :value="__('Bio')" />
            <textarea id="bio" name="bio" rows="4"
                      class="mt-1 block w-full rounded-xl border-line bg-card text-ink shadow-sm placeholder:text-ink/40 focus:border-crimson focus:ring-crimson"
                      placeholder="{{ __('A short introduction (up to 1000 characters).') }}">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div>
            <span class="block text-sm font-medium text-ink">{{ __('Learning preferences') }}</span>
            <label for="email_digest" class="mt-2 inline-flex items-center gap-2">
                <input type="hidden" name="email_digest" value="0">
                <input id="email_digest" type="checkbox" name="email_digest" value="1"
                       @checked(old('email_digest', $user->wantsEmailDigest()))
                       class="rounded border-line text-crimson shadow-sm focus:ring-crimson">
                <span class="text-sm text-ink/70">{{ __('Email me a periodic learning digest') }}</span>
            </label>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-ink/70"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
