<x-app-layout title="Invitations">
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 rounded text-sm text-ink/60 hover:text-crimson focus-ring">
                <x-ui.icon name="chevron-left" class="h-4 w-4" /> Back to people
            </a>
            <h2 class="mt-2 font-display text-2xl font-semibold text-ink">Invitations</h2>
            <p class="mt-1 text-ink/70">Invite someone by email — they choose their own password from a secure, expiring link.</p>
        </div>

        {{-- Invite form --}}
        <x-ui.card>
            <h3 class="font-display text-lg font-semibold text-ink">Send an invitation</h3>
            <form method="POST" action="{{ route('admin.invitations.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                @csrf
                <x-ui.field name="name" label="Name" required :value="old('name')" class="lg:col-span-1" />
                <x-ui.field name="email" label="Email" type="email" required :value="old('email')" class="lg:col-span-1" />
                <x-ui.field name="role" label="Role" required class="lg:col-span-1">
                    <select id="role" name="role"
                            class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <div class="lg:col-span-1">
                    <x-ui.button type="submit" class="w-full">
                        <x-ui.icon name="mail" class="h-5 w-5" /> Send invitation
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- Invitation list — re-send / revoke run without a full-page reload. --}}
        <div x-data="dataTable('{{ route('admin.invitations.index') }}')">
            <div id="invitations-results" x-ref="results"
                 @click="onNav($event)" @submit="onAction($event)"
                 :class="loading && 'pointer-events-none opacity-60 transition-opacity'"
                 :aria-busy="loading.toString()">
                @include('admin.invitations._table')
            </div>
        </div>
    </div>
</x-app-layout>
