@php
    use App\Enums\NotificationType;
    use App\Notifications\UprlNotification;

    /** @var \Illuminate\Pagination\LengthAwarePaginator $notifications */
    /** @var string $filter */

    // Resolve a stored notification to its catalogue type (icon + tone), gracefully
    // falling back for anything unrecognised.
    $typeOf = function ($notification): ?NotificationType {
        $class = $notification->type;

        return is_string($class) && is_subclass_of($class, UprlNotification::class) ? $class::type() : null;
    };

    // A human day-bucket header ("Today" / "Yesterday" / "12 July 2026") so the list
    // reads as a timeline, not an undifferentiated stack.
    $bucket = function ($date): string {
        if ($date->isToday()) return 'Today';
        if ($date->isYesterday()) return 'Yesterday';
        if ($date->isCurrentWeek()) return $date->isoFormat('dddd');

        return $date->isoFormat('D MMMM YYYY');
    };

    $lastBucket = null;
@endphp

<x-app-layout title="Notifications">
    <div class="mx-auto max-w-2xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Notifications</h2>
                <p class="mt-1 text-sm text-ink/65">
                    @if ($unreadCount > 0)
                        You have <span class="font-semibold text-crimson">{{ $unreadCount }}</span> unread {{ Str::plural('update', $unreadCount) }}.
                    @else
                        You're all caught up.
                    @endif
                </p>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        <x-ui.icon name="check" class="h-4 w-4" /> Mark all read
                    </x-ui.button>
                </form>
            @endif
        </div>

        {{-- Filter pills --}}
        <div class="inline-flex rounded-xl border border-line bg-card p-1 text-sm shadow-sm">
            <a href="{{ route('notifications.index') }}"
               @class(['rounded-lg px-4 py-1.5 font-medium focus-ring transition-colors', 'bg-crimson text-white shadow-sm' => $filter !== 'unread', 'text-ink/65 hover:text-ink' => $filter === 'unread'])>
                All
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
               @class(['inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 font-medium focus-ring transition-colors', 'bg-crimson text-white shadow-sm' => $filter === 'unread', 'text-ink/65 hover:text-ink' => $filter !== 'unread'])>
                Unread
                @if ($unreadCount > 0)
                    <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20 text-white' => $filter === 'unread', 'bg-crimson/10 text-crimson' => $filter !== 'unread'])>{{ $unreadCount }}</span>
                @endif
            </a>
        </div>

        @if ($notifications->isEmpty())
            <x-ui.empty-state icon="bell-slash"
                :title="$filter === 'unread' ? 'Nothing unread' : 'No notifications yet'"
                :description="$filter === 'unread'
                    ? 'Every notification has been read. New ones will appear here.'
                    : 'Updates about your enrolments, grades, certificates and courses will land here.'" />
        @else
            <div class="space-y-5">
                @foreach ($notifications as $notification)
                    @php
                        $type = $typeOf($notification);
                        $tile = NotificationType::toneClasses($type?->tone() ?? 'gold');
                        $icon = $type?->icon() ?? 'bell';
                        $unread = $notification->read_at === null;
                        $thisBucket = $bucket($notification->created_at);
                        $newBucket = $thisBucket !== $lastBucket;
                        $lastBucket = $thisBucket;
                    @endphp

                    @if ($newBucket)
                        <p class="px-1 pt-1 text-xs font-semibold uppercase tracking-[0.14em] text-ink/65 {{ ! $loop->first ? 'mt-2' : '' }}">
                            {{ $thisBucket }}
                        </p>
                    @endif

                    <a href="{{ route('notifications.open', $notification) }}"
                       @class([
                           'group relative flex items-start gap-4 overflow-hidden rounded-2xl border bg-card p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md focus-ring',
                           'border-crimson/20' => $unread,
                           'border-line' => ! $unread,
                       ])>
                        @if ($unread)
                            <span class="absolute inset-y-0 left-0 w-1 bg-crimson" aria-hidden="true"></span>
                        @endif

                        <span class="mt-0.5 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tile }}">
                            <x-ui.icon :name="$icon" class="h-5 w-5" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate text-ink {{ $unread ? 'font-semibold' : 'font-medium' }}">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                @if ($unread)
                                    <span class="shrink-0 rounded-full bg-crimson/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-crimson">New</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block text-sm leading-relaxed text-ink/70">{{ $notification->data['body'] ?? '' }}</span>
                            <span class="mt-1.5 block text-xs text-ink/65">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>

                        <x-ui.icon name="chevron-right" class="mt-3 h-4 w-4 shrink-0 text-ink/25 transition-transform group-hover:translate-x-0.5 group-hover:text-crimson" />
                    </a>
                @endforeach
            </div>

            @if ($notifications->hasPages())
                <div>{{ $notifications->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
