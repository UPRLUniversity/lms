@php
    /** @var \App\Models\ForumPost $post */
    /** @var \App\Models\Course $course */
    /** @var \App\Models\ForumThread $thread */
    /** @var array<int,int> $instructorIds */
    $isReply = $isReply ?? false;
    $user = auth()->user();
    $isAnswer = $thread->answer_post_id === $post->id;
    $authorIsInstructor = in_array($post->author?->id, $instructorIds, true);
@endphp

<div id="post-{{ $post->id }}"
     @class([
         'rounded-2xl border p-5 shadow-sm scroll-mt-24',
         'border-success/40 bg-success/[0.04]' => $isAnswer,
         'border-line bg-card' => ! $isAnswer,
     ])>
    <div class="flex items-start gap-3">
        <x-ui.avatar :user="$post->author" size="sm" class="mt-0.5" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-ink">{{ $post->author?->name ?? 'A member' }}</span>
                @if ($authorIsInstructor)
                    <x-ui.badge variant="gold"><x-ui.icon name="academic-cap" class="h-3 w-3" /> Instructor</x-ui.badge>
                @endif
                @if ($isAnswer)
                    <x-ui.badge variant="success"><x-ui.icon name="check" class="h-3 w-3" /> Answer</x-ui.badge>
                @endif
                <time class="text-xs text-ink/65" datetime="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</time>
            </div>

            <x-ui.prose class="mt-2 text-sm" :html="$post->body" />

            {{-- Actions --}}
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs">
                @if (! $isReply && $canReply)
                    <button type="button" x-data @click="$dispatch('toggle-reply-{{ $post->id }}')"
                            class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-crimson focus-ring rounded">
                        <x-ui.icon name="chat" class="h-3.5 w-3.5" /> Reply
                    </button>
                @endif

                @if (! $isReply && $canMarkAnswer)
                    <form method="POST" action="{{ route('forum.answer', [$course, $thread]) }}">
                        @csrf
                        <input type="hidden" name="post_id" value="{{ $post->id }}">
                        <button type="submit" class="inline-flex items-center gap-1 font-medium {{ $isAnswer ? 'text-success' : 'text-ink/65 hover:text-success' }} focus-ring rounded">
                            <x-ui.icon name="check-circle" class="h-3.5 w-3.5" />
                            {{ $isAnswer ? 'Unmark answer' : 'Mark as answer' }}
                        </button>
                    </form>
                @endif

                @can('report', $post)
                    <form method="POST" action="{{ route('posts.report', $post) }}" x-data
                          @submit.prevent="if (await window.uprlConfirm({ title: 'Report this post?', text: 'An admin will review it. Add nothing more to do — we\'ll take it from here.', confirmText: 'Report', icon: 'warning' })) $el.submit()">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-crimson focus-ring rounded">
                            <x-ui.icon name="flag" class="h-3.5 w-3.5" /> Report
                        </button>
                    </form>
                @endcan

                @can('delete', $post)
                    <form method="POST" action="{{ route('posts.destroy', $post) }}" x-data
                          @submit.prevent="if (await window.uprlConfirm({ title: 'Remove this reply?', text: 'It will be hidden from the discussion.', confirmText: 'Yes, remove' })) $el.submit()">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1 font-medium text-ink/65 hover:text-crimson focus-ring rounded">
                            <x-ui.icon name="trash" class="h-3.5 w-3.5" /> Remove
                        </button>
                    </form>
                @endcan
            </div>

            {{-- Inline reply form (top-level posts only) --}}
            @if (! $isReply && $canReply)
                <div x-data="{ open: false }" @toggle-reply-{{ $post->id }}.window="open = ! open; if (open) $nextTick(() => $refs.replybody?.focus())"
                     x-show="open" x-cloak class="mt-4">
                    <form method="POST" action="{{ route('posts.store', [$course, $thread]) }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $post->id }}">
                        <label for="reply-{{ $post->id }}" class="sr-only">Reply to {{ $post->author?->name }}</label>
                        <textarea id="reply-{{ $post->id }}" name="body" rows="3" x-ref="replybody" required
                                  class="block w-full rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                  placeholder="Write a reply…"></textarea>
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" size="sm" variant="ghost" @click="open = false">Cancel</x-ui.button>
                            <x-ui.button type="submit" size="sm">Post reply</x-ui.button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Nested replies (one level) --}}
            @if (! $isReply && $post->replies->isNotEmpty())
                <div class="mt-4 space-y-3 border-l-2 border-line pl-4">
                    @foreach ($post->replies as $reply)
                        @include('learn.forum._post', ['post' => $reply, 'isReply' => true])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
