@php
    $sort ??= 'name';
    $direction ??= 'asc';
    $search ??= '';
    $activeRole ??= '';

    $canManage = auth()->user()->can('create', \App\Models\User::class);

    // Build an AJAX-friendly sortable column header. Preserves the current
    // search/role filters and flips direction on the active column.
    $header = function (string $key, string $label) use ($sort, $direction) {
        $isActive = $sort === $key;
        $nextDir = $isActive && $direction === 'asc' ? 'desc' : 'asc';
        $url = request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDir, 'page' => 1]);
        $ariaSort = $isActive ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';

        return [
            'url' => $url,
            'isActive' => $isActive,
            'arrow' => $isActive ? ($direction === 'asc' ? '↑' : '↓') : '',
            'ariaSort' => $ariaSort,
            'label' => $label,
        ];
    };

    $cols = [
        'name' => $header('name', 'Name'),
        'status' => $header('status', 'Status'),
        'last_login' => $header('last_login', 'Last login'),
    ];
@endphp

<div class="flex items-center justify-between gap-3 px-1 pb-3 text-sm text-ink/60">
    <p aria-live="polite">
        {{ $users->total() }} {{ \Illuminate\Support\Str::plural('person', $users->total()) }}
        @if ($search !== '' || $activeRole !== '')
            <span class="text-ink/40">· filtered</span>
        @endif
    </p>
</div>

<x-ui.card :padding="false">
    @if ($users->isEmpty())
        <div class="p-5">
            <x-ui.empty-state
                icon="users"
                title="No people match your filters"
                description="Try a different search term or clear the role filter." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/50">
                    <tr>
                        @foreach (['name', 'status', 'last_login'] as $key)
                            @php $c = $cols[$key]; @endphp
                            <th scope="col" class="px-5 py-3 font-medium" aria-sort="{{ $c['ariaSort'] }}">
                                <a href="{{ $c['url'] }}" data-nav
                                   @class([
                                       'group inline-flex items-center gap-1 rounded focus-ring',
                                       'text-crimson' => $c['isActive'],
                                       'hover:text-ink' => ! $c['isActive'],
                                   ])>
                                    {{ $c['label'] }}
                                    <span aria-hidden="true" class="text-[10px] {{ $c['isActive'] ? '' : 'opacity-0 group-hover:opacity-40' }}">{{ $c['arrow'] ?: '↕' }}</span>
                                </a>
                            </th>
                        @endforeach
                        <th scope="col" class="px-5 py-3 font-medium">Roles</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">
                            <span class="@if (! $canManage) sr-only @endif">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($users as $person)
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :user="$person" size="sm" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-ink">{{ $person->name }}</p>
                                        <p class="truncate text-xs text-ink/60">{{ $person->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                @if ($person->is_active)
                                    <x-ui.badge variant="success">Active</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">Deactivated</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink/60">
                                {{ $person->last_login_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($person->roles as $role)
                                        <x-ui.role-badge :role="$role->name" />
                                    @empty
                                        <span class="text-xs text-ink/40">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                @can('update', $person)
                                    <div class="flex items-center justify-end gap-2">
                                        <x-ui.button size="sm" variant="ghost" :href="route('admin.users.edit', $person)">
                                            <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                        </x-ui.button>

                                        @can('setActiveStatus', $person)
                                            <form method="POST" action="{{ route('admin.users.status', $person) }}" data-ajax>
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="is_active" value="{{ $person->is_active ? 0 : 1 }}">
                                                <x-ui.button size="sm" :variant="$person->is_active ? 'danger' : 'secondary'" type="submit">
                                                    {{ $person->is_active ? 'Deactivate' : 'Reactivate' }}
                                                </x-ui.button>
                                            </form>
                                        @endcan
                                    </div>
                                @else
                                    <span class="sr-only">No actions available</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>

<div class="mt-4">
    {{ $users->links('pagination.uprl') }}
</div>
