@php
    /** @var \App\Models\Conversation $conversation */
    $me = auth()->user();
    $others = $conversation->participants->reject(fn ($p) => $p->id === $me->id)->values();
@endphp

{{-- Header --}}
<div class="flex items-center gap-3 border-b border-line px-4 py-3">
    <a href="{{ route('messages.index') }}" class="rounded-lg p-1.5 text-ink/50 hover:bg-ink/5 hover:text-ink focus-ring lg:hidden" aria-label="Back to conversations">
        <x-ui.icon name="arrow-left" class="h-5 w-5" />
    </a>
    @if ($conversation->isGroup())
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold/15 text-gold-ink">
            <x-ui.icon name="chat-group" class="h-4 w-4" />
        </span>
    @else
        <x-ui.avatar :user="$conversation->otherParticipant($me)" size="sm" />
    @endif
    <div class="min-w-0 flex-1">
        <h2 class="truncate font-display text-base font-semibold text-ink">{{ $conversation->titleFor($me) }}</h2>
        <p class="truncate text-xs text-ink/55">
            @if ($conversation->isGroup())
                {{ $conversation->participants->count() }} participants
                @if ($conversation->course) · {{ $conversation->course->code }} @endif
                — {{ $others->take(3)->pluck('name')->join(', ') }}{{ $others->count() > 3 ? ', …' : '' }}
            @else
                Direct message
            @endif
        </p>
    </div>
</div>

{{-- Messages --}}
<div class="flex-1 space-y-4 overflow-y-auto px-4 py-5" x-data x-init="$el.scrollTop = $el.scrollHeight">
    @forelse ($conversation->messages as $message)
        @php
            $mine = $message->user_id === $me->id;
            $attachment = $message->attachment();
        @endphp
        <div @class(['flex gap-2.5', 'flex-row-reverse' => $mine])>
            @unless ($mine)
                <x-ui.avatar :user="$message->sender" size="xs" class="mt-1" />
            @endunless
            <div @class(['max-w-[75%]', 'text-right' => $mine])>
                @if ($conversation->isGroup() && ! $mine)
                    <p class="mb-0.5 text-[11px] font-medium text-ink/55">{{ $message->sender?->name }}</p>
                @endif
                <div @class([
                    'inline-block rounded-2xl px-4 py-2.5 text-sm shadow-sm text-left',
                    'bg-crimson text-white' => $mine,
                    'bg-surface text-ink border border-line' => ! $mine,
                ])>
                    <x-ui.prose :html="$message->body" @class(['text-sm uprl-prose-chat', 'uprl-prose-invert' => $mine]) />

                    @if ($attachment)
                        <a href="{{ route('media.download', $attachment) }}"
                           @class([
                               'mt-2 flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-medium',
                               'bg-white/15 text-white hover:bg-white/25' => $mine,
                               'bg-card text-ink border border-line hover:bg-surface' => ! $mine,
                           ])>
                            <x-ui.icon name="document" class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ $attachment->original_name }}</span>
                            <x-ui.icon name="download" class="h-3.5 w-3.5 shrink-0" />
                        </a>
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-ink/40">{{ $message->created_at->diffForHumans(short: true) }}</p>
            </div>
        </div>
    @empty
        <p class="py-10 text-center text-sm text-ink/45">No messages yet — say hello.</p>
    @endforelse
</div>

{{-- Composer --}}
<div class="border-t border-line p-3">
    <form method="POST" action="{{ route('messages.send', $conversation) }}" enctype="multipart/form-data"
          class="space-y-2" x-data="{ fileName: '' }">
        @csrf
        <x-ui.rich-editor name="body" :value="old('body')" height="110" profile="basic"
                          placeholder="Write a message…" aria-label="Message" />

        @error('body') <p class="text-xs text-crimson">{{ $message }}</p> @enderror
        @error('attachment') <p class="text-xs text-crimson">{{ $message }}</p> @enderror

        <div class="flex items-center justify-between gap-3">
            <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium text-ink/60 hover:text-crimson focus-within:text-crimson">
                <x-ui.icon name="link" class="h-4 w-4" />
                <span x-text="fileName || 'Attach a file'"></span>
                <input type="file" name="attachment" class="sr-only"
                       x-on:change="fileName = $event.target.files[0]?.name ?? ''">
            </label>
            <x-ui.button type="submit" size="sm">
                Send <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </x-ui.button>
        </div>
    </form>
</div>
