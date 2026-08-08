@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\ForumThread $thread */
    $instructorIds = $course->instructors->pluck('id')->all();
    $threadAuthorIsInstructor = in_array($thread->author?->id, $instructorIds, true);
@endphp

<x-app-layout :title="$thread->title.' · '.$course->title">
    <div class="mx-auto max-w-3xl space-y-6">
        <a href="{{ route('forum.index', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-crimson focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to forum
        </a>

        {{-- Thread card --}}
        <article class="rounded-2xl border border-line bg-card p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                @if ($thread->isPinned())
                    <x-ui.badge variant="crimson"><x-ui.icon name="flag" class="h-3 w-3" /> Pinned</x-ui.badge>
                @endif
                @if ($thread->isAnswered())
                    <x-ui.badge variant="success"><x-ui.icon name="check" class="h-3 w-3" /> Answered</x-ui.badge>
                @endif
                @if ($thread->isLocked())
                    <x-ui.badge variant="neutral"><x-ui.icon name="lock" class="h-3 w-3" /> Locked</x-ui.badge>
                @endif
                @if ($thread->lesson)
                    <x-ui.badge variant="ink"><x-ui.icon name="document-text" class="h-3 w-3" /> {{ $thread->lesson->title }}</x-ui.badge>
                @endif
            </div>

            <h1 class="mt-3 font-display text-2xl font-semibold leading-tight text-ink">{{ $thread->title }}</h1>

            <div class="mt-3 flex items-center gap-2">
                <x-ui.avatar :user="$thread->author" size="sm" />
                <div class="text-sm">
                    <span class="font-medium text-ink">{{ $thread->author?->name ?? 'A member' }}</span>
                    @if ($threadAuthorIsInstructor)
                        <x-ui.badge variant="gold" class="ml-1"><x-ui.icon name="academic-cap" class="h-3 w-3" /> Instructor</x-ui.badge>
                    @endif
                    <span class="text-ink/65">· {{ $thread->created_at->diffForHumans() }}</span>
                </div>
            </div>

            <x-ui.prose class="mt-4" :html="$thread->body" />

            {{-- Moderation toolbar --}}
            @if ($canModerate)
                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-4">
                    <form method="POST" action="{{ route('forum.pin', [$course, $thread]) }}">
                        @csrf
                        <x-ui.button type="submit" size="sm" variant="secondary">
                            <x-ui.icon name="flag" class="h-4 w-4" /> {{ $thread->isPinned() ? 'Unpin' : 'Pin' }}
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('forum.lock', [$course, $thread]) }}">
                        @csrf
                        <x-ui.button type="submit" size="sm" variant="secondary">
                            <x-ui.icon name="lock" class="h-4 w-4" /> {{ $thread->isLocked() ? 'Unlock' : 'Lock' }}
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('forum.destroy', [$course, $thread]) }}" x-data
                          @submit.prevent="if (await window.uprlConfirm({ title: 'Remove this thread?', text: 'The whole discussion will be hidden.', confirmText: 'Yes, remove' })) $el.submit()">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" size="sm" variant="danger">
                            <x-ui.icon name="trash" class="h-4 w-4" /> Remove thread
                        </x-ui.button>
                    </form>
                </div>
            @endif
        </article>

        {{-- Accepted answer (surfaced above the rest when the thread is resolved) --}}
        @if ($thread->isAnswered() && $thread->answer)
            <section aria-label="Accepted answer">
                <p class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-success">
                    <x-ui.icon name="check-circle" class="h-4 w-4" /> Accepted answer
                </p>
                @include('learn.forum._post', ['post' => $thread->answer, 'isReply' => false])
            </section>
        @endif

        {{-- Replies --}}
        <section aria-label="Replies" class="space-y-4">
            <h2 class="font-display text-lg font-semibold text-ink">
                {{ $thread->posts->count() }} {{ \Illuminate\Support\Str::plural('reply', $thread->posts->count()) }}
            </h2>

            @forelse ($thread->posts as $post)
                @include('learn.forum._post', ['post' => $post, 'isReply' => false])
            @empty
                <p class="rounded-2xl border border-dashed border-line bg-card px-5 py-8 text-center text-sm text-ink/65">
                    No replies yet. @if ($canReply) Be the first to respond. @endif
                </p>
            @endforelse
        </section>

        {{-- New reply composer --}}
        @if ($canReply)
            <section aria-labelledby="reply-heading" class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                <h2 id="reply-heading" class="font-display text-base font-semibold text-ink">Post a reply</h2>
                <form method="POST" action="{{ route('posts.store', [$course, $thread]) }}" class="mt-3 space-y-3">
                    @csrf
                    <x-ui.rich-editor name="body" label="Your reply" :value="old('body')" height="160" profile="basic"
                                      placeholder="Share your thoughts or an answer…" required />
                    <div class="flex justify-end">
                        <x-ui.button type="submit"><x-ui.icon name="chat" class="h-4 w-4" /> Post reply</x-ui.button>
                    </div>
                </form>
            </section>
        @elseif ($thread->isLocked())
            <div class="flex items-center justify-center gap-2 rounded-2xl border border-dashed border-line bg-surface px-5 py-6 text-sm text-ink/65">
                <x-ui.icon name="lock" class="h-4 w-4" /> This thread is locked — no new replies.
            </div>
        @endif
    </div>
</x-app-layout>
