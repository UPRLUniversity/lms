@php
    use App\Enums\EnrollmentStatus;

    $search ??= '';
    $activeStatus ??= '';
    $canManage ??= false;
    $canApprove ??= false;
@endphp

<div class="flex items-center justify-between gap-3 px-1 pb-3 text-sm text-ink/60">
    <p aria-live="polite">
        {{ $enrollments->total() }} {{ \Illuminate\Support\Str::plural('student', $enrollments->total()) }}
        @if ($search !== '' || $activeStatus !== '')
            <span class="text-ink/40">· filtered</span>
        @endif
    </p>
</div>

<x-ui.card :padding="false">
    @if ($enrollments->isEmpty())
        <div class="p-5">
            <x-ui.empty-state
                icon="users"
                title="No students here yet"
                description="{{ $search !== '' || $activeStatus !== '' ? 'Try a different search or status filter.' : 'When students enrol, they will appear in this roster.' }}" />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/50">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Student</th>
                        <th scope="col" class="px-5 py-3 font-medium">Status</th>
                        <th scope="col" class="px-5 py-3 font-medium">Source</th>
                        <th scope="col" class="px-5 py-3 font-medium">Enrolled</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">
                            <span class="@if (! $canManage && ! $canApprove) sr-only @endif">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($enrollments as $enrollment)
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :user="$enrollment->user" size="sm" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-ink">{{ $enrollment->user->name }}</p>
                                        <p class="truncate text-xs text-ink/60">{{ $enrollment->user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-ui.badge :variant="$enrollment->status->badge()">
                                    @if ($enrollment->status === EnrollmentStatus::Waitlisted)
                                        Waitlisted #{{ $enrollment->waitlistPosition() }}
                                    @else
                                        {{ $enrollment->status->label() }}
                                    @endif
                                </x-ui.badge>
                            </td>
                            <td class="px-5 py-3 text-ink/60">{{ $enrollment->source->label() }}</td>
                            <td class="px-5 py-3 text-ink/60">{{ $enrollment->enrolled_at?->isoFormat('D MMM YYYY') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($canApprove && $enrollment->status === EnrollmentStatus::Pending)
                                        <form method="POST" action="{{ route('enrollments.approve', $enrollment) }}" data-ajax>
                                            @csrf
                                            <x-ui.button size="sm" variant="secondary" type="submit">
                                                <x-ui.icon name="check" class="h-4 w-4" /> Approve
                                            </x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('enrollments.reject', $enrollment) }}" data-ajax
                                              data-confirm="Decline this request?" data-confirm-action="Yes, decline">
                                            @csrf
                                            <x-ui.button size="sm" variant="danger" type="submit">Decline</x-ui.button>
                                        </form>
                                    @endif

                                    @if ($canManage && in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Pending, EnrollmentStatus::Waitlisted], true))
                                        <form method="POST" action="{{ route('courses.roster.withdraw', [$course, $enrollment]) }}" data-ajax
                                              data-confirm="Withdraw {{ $enrollment->user->name }}?" data-confirm-action="Yes, withdraw">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button size="sm" variant="ghost" type="submit">
                                                <x-ui.icon name="trash" class="h-4 w-4" /> Withdraw
                                            </x-ui.button>
                                        </form>
                                    @endif

                                    @if (! $canManage && ! $canApprove)
                                        <span class="sr-only">No actions available</span>
                                    @endif
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
    {{ $enrollments->links('pagination.uprl') }}
</div>
