<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Profile photo') }}</h2>
        <p class="mt-1 text-sm text-ink/70">
            {{ __('Upload a square image (JPG, PNG or WebP, up to 2 MB). We fall back to your initials.') }}
        </p>
    </header>

    <div class="mt-6 flex flex-wrap items-center gap-6">
        <x-ui.avatar :user="$user" size="xl" />

        <div class="space-y-3">
            <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-center gap-3">
                @csrf

                <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-card px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface focus-within:ring-2 focus-within:ring-crimson">
                    <x-ui.icon name="camera" class="h-5 w-5" />
                    <span>{{ __('Choose image') }}</span>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                           class="sr-only"
                           x-on:change="$el.closest('form').querySelector('[data-upload]').removeAttribute('disabled'); $el.nextElementSibling && ($el.parentElement.querySelector('span').textContent = $el.files[0]?.name ?? 'Choose image')">
                </label>

                <x-ui.button type="submit" size="sm" data-upload disabled>{{ __('Upload') }}</x-ui.button>
            </form>

            <x-input-error :messages="$errors->get('avatar')" />

            @if ($user->avatar())
                <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="rounded text-sm text-ink/60 underline hover:text-crimson focus-ring">
                        {{ __('Remove photo') }}
                    </button>
                </form>
            @endif

            @if (session('status') === 'avatar-updated')
                <p class="text-sm text-success">{{ __('Photo updated.') }}</p>
            @elseif (session('status') === 'avatar-removed')
                <p class="text-sm text-ink/70">{{ __('Photo removed.') }}</p>
            @endif
        </div>
    </div>
</section>
