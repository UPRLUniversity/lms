<x-ui.card :padding="false">
    @if ($invitations->isEmpty())
        <div class="p-5">
            <x-ui.empty-state
                icon="inbox"
                title="No invitations yet"
                description="Invitations you send will be listed here with their status." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/50">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Invitee</th>
                        <th scope="col" class="px-5 py-3 font-medium">Role</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium">Expires</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($invitations as $invitation)
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink">{{ $invitation->name }}</p>
                                <p class="text-xs text-ink/60">{{ $invitation->email }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <x-ui.role-badge :role="$invitation->role" />
                            </td>
                            <td class="px-5 py-3">
                                @switch($invitation->status())
                                    @case('accepted')
                                        <x-ui.badge variant="success">Accepted</x-ui.badge>
                                        @break
                                    @case('expired')
                                        <x-ui.badge variant="neutral">Expired</x-ui.badge>
                                        @break
                                    @default
                                        <x-ui.badge variant="crimson">Pending</x-ui.badge>
                                @endswitch
                            </td>
                            <td class="px-5 py-3 text-ink/60">
                                {{ $invitation->expires_at->diffForHumans() }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @unless ($invitation->isAccepted())
                                        <form method="POST" action="{{ route('admin.invitations.resend', $invitation) }}" data-ajax>
                                            @csrf
                                            <x-ui.button size="sm" variant="secondary" type="submit">Re-send</x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.invitations.destroy', $invitation) }}"
                                              data-ajax data-confirm="Revoke this invitation?">
                                            @csrf
                                            @method('delete')
                                            <x-ui.button size="sm" variant="ghost" type="submit" aria-label="Revoke invitation">
                                                <x-ui.icon name="trash" class="h-4 w-4" />
                                            </x-ui.button>
                                        </form>
                                    @else
                                        <span class="text-xs text-ink/40">—</span>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>

<div class="mt-4">
    {{ $invitations->links('pagination.uprl') }}
</div>
