<x-app-layout title="Edit user">
    @php $canAssignRoles = auth()->user()->can('assignRoles', \App\Models\User::class); @endphp

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 rounded text-sm text-ink/60 hover:text-crimson focus-ring">
                <x-ui.icon name="chevron-left" class="h-4 w-4" /> Back to people
            </a>
            <div class="mt-2 flex items-center gap-3">
                <x-ui.avatar :user="$user" size="lg" />
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $user->name }}</h2>
                    <p class="text-ink/70">{{ $user->email }}</p>
                </div>
            </div>
        </div>

        <x-ui.card>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                @csrf
                @method('put')

                <x-ui.field name="name" label="Full name" required :value="old('name', $user->name)" autocomplete="name" />
                <x-ui.field name="email" label="Email" type="email" required :value="old('email', $user->email)" autocomplete="email" />

                <div class="grid gap-6 sm:grid-cols-2">
                    <x-ui.field name="phone" label="Phone" :value="old('phone', $user->phone)" autocomplete="tel" />
                    <x-ui.field name="title" label="Title" :value="old('title', $user->title)" />
                </div>

                <x-ui.field name="role" label="Role" required
                            :hint="$canAssignRoles ? null : 'You do not have permission to change roles.'">
                    <select id="role" name="role" @disabled(! $canAssignRoles)
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson disabled:opacity-60">
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}" @selected(old('role', $currentRole) === $r->value)>{{ $r->label() }}</option>
                        @endforeach
                        {{-- Show the user's existing privileged role even if this admin can't grant it. --}}
                        @if ($currentRole && ! collect($roles)->contains(fn ($r) => $r->value === $currentRole))
                            <option value="{{ $currentRole }}" selected>{{ \App\Enums\Role::from($currentRole)->label() }}</option>
                        @endif
                    </select>
                </x-ui.field>

                <div class="flex items-center justify-between gap-3">
                    <div>
                        @if ($user->is_active)
                            <x-ui.badge variant="success">Active</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral">Deactivated</x-ui.badge>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui.button variant="ghost" :href="route('admin.users.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit">Save changes</x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card>

        @can('setActiveStatus', $user)
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-lg font-semibold text-ink">
                            {{ $user->is_active ? 'Deactivate account' : 'Reactivate account' }}
                        </h3>
                        <p class="mt-1 text-sm text-ink/70">
                            {{ $user->is_active
                                ? 'A deactivated user keeps all their data but cannot sign in.'
                                : 'Restore this user’s ability to sign in.' }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                        <x-ui.button type="submit" :variant="$user->is_active ? 'danger' : 'secondary'">
                            {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                        </x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        @endcan
    </div>
</x-app-layout>
