<x-app-layout title="New user">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 rounded text-sm text-ink/60 hover:text-crimson focus-ring">
                <x-ui.icon name="chevron-left" class="h-4 w-4" /> Back to people
            </a>
            <h2 class="mt-2 font-display text-2xl font-semibold text-ink">Create a user</h2>
            <p class="mt-1 text-ink/70">The account is created verified and active. Share the password securely, or use an invitation instead.</p>
        </div>

        <x-ui.card>
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
                @csrf

                <x-ui.field name="name" label="Full name" required :value="old('name')" autofocus autocomplete="name" />
                <x-ui.field name="email" label="Email" type="email" required :value="old('email')" autocomplete="email" />

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-ui.field name="phone" label="Phone" hint="Optional" :value="old('phone')" autocomplete="tel" />
                    <x-ui.field name="title" label="Title" hint="e.g. Senior Lecturer" :value="old('title')" />
                </div>

                <x-ui.field name="role" label="Role" required>
                    <select id="role" name="role"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <x-input-label for="password" :value="__('Password')" />
                        <x-ui.password id="password" name="password" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" />
                    </div>
                    <div class="space-y-1.5">
                        <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                        <x-ui.password id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button variant="ghost" :href="route('admin.users.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create user</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
