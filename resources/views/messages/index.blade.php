@php
    /** @var \Illuminate\Support\Collection $conversations */
    /** @var \App\Models\Conversation|null $active */
    $me = auth()->user();
@endphp

<x-app-layout title="Messages">
    <div class="mx-auto max-w-6xl">
        <div class="grid gap-4 lg:grid-cols-[20rem_1fr] lg:gap-6">
            {{-- Conversation list --}}
            <aside @class([
                'flex flex-col rounded-2xl border border-line bg-card shadow-sm lg:h-[calc(100vh-8rem)]',
                'hidden lg:flex' => $active,
            ]) aria-label="Conversations">
                <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <h2 class="font-display text-lg font-semibold text-ink">Messages</h2>
                    <x-ui.button size="sm" :href="route('messages.create')">
                        <x-ui.icon name="plus" class="h-4 w-4" /> New
                    </x-ui.button>
                </div>

                <div class="flex-1 overflow-y-auto p-2">
                    @forelse ($conversations as $conversation)
                        @php
                            $unread = $conversation->unread_count ?? 0;
                            $isActive = $active && $active->id === $conversation->id;
                            $preview = $conversation->latestMessage
                                ? \Illuminate\Support\Str::limit(trim(strip_tags((string) $conversation->latestMessage->body)), 48)
                                : 'No messages yet';
                        @endphp
                        <a href="{{ route('messages.show', $conversation) }}"
                           @class([
                               'flex items-start gap-3 rounded-xl px-3 py-2.5 transition focus-ring',
                               'bg-crimson/10' => $isActive,
                               'hover:bg-ink/5' => ! $isActive,
                           ])
                           @if ($isActive) aria-current="page" @endif>
                            @if ($conversation->isGroup())
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold/10 text-gold-ink">
                                    <x-ui.icon name="chat-group" class="h-4 w-4" />
                                </span>
                            @else
                                <x-ui.avatar :user="$conversation->otherParticipant($me)" size="sm" />
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm {{ $unread ? 'font-bold text-ink' : 'font-medium text-ink/90' }}">
                                        {{ $conversation->titleFor($me) }}
                                    </span>
                                    <span class="shrink-0 text-[11px] text-ink/65">
                                        {{ $conversation->last_message_at?->diffForHumans(short: true) }}
                                    </span>
                                </span>
                                <span class="mt-0.5 flex items-center justify-between gap-2">
                                    <span class="truncate text-xs {{ $unread ? 'font-semibold text-ink/70' : 'text-ink/65' }}">{{ $preview }}</span>
                                    @if ($unread)
                                        <span class="inline-flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-crimson px-1.5 text-[11px] font-semibold text-white">
                                            {{ $unread > 9 ? '9+' : $unread }}
                                        </span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    @empty
                        <div class="px-3 py-10 text-center">
                            <p class="text-sm text-ink/65">No conversations yet.</p>
                            <a href="{{ route('messages.create') }}" class="mt-2 inline-block text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">Start one →</a>
                        </div>
                    @endforelse
                </div>
            </aside>

            {{-- Thread pane --}}
            <section class="flex flex-col rounded-2xl border border-line bg-card shadow-sm lg:h-[calc(100vh-8rem)]" aria-label="Conversation">
                @if ($active)
                    @include('messages.partials._thread', ['conversation' => $active])
                @else
                    <div class="flex flex-1 items-center justify-center p-8">
                        <x-ui.empty-state icon="chat" title="Your messages"
                            description="Pick a conversation on the left, or start a new one to message an instructor or a coursemate." class="border-0 bg-transparent shadow-none">
                            <x-slot name="action">
                                <x-ui.button :href="route('messages.create')"><x-ui.icon name="plus" class="h-4 w-4" /> New message</x-ui.button>
                            </x-slot>
                        </x-ui.empty-state>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
