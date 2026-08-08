@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $reports */
@endphp

<x-app-layout title="Reported posts">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                <x-ui.icon name="flag" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Reported posts</h2>
                <p class="text-sm text-ink/65">{{ $openCount }} open {{ \Illuminate\Support\Str::plural('report', $openCount) }} awaiting review.</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse ($reports as $report)
                @php $post = $report->post; @endphp
                <article class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs text-ink/65">
                                Reported by <span class="font-medium text-ink">{{ $report->reporter?->name ?? 'A member' }}</span>
                                · {{ $report->created_at->diffForHumans() }}
                                @if ($post?->thread?->course)
                                    · in <a href="{{ route('forum.show', [$post->thread->course, $post->thread]) }}" class="font-medium text-crimson hover:text-crimson-dark">{{ $post->thread->course->code }}</a>
                                @endif
                            </p>
                            @if ($report->reason)
                                <p class="mt-1 text-sm text-ink/80"><span class="font-medium">Reason:</span> {{ $report->reason }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 rounded-xl border border-line bg-surface p-4">
                        @if ($post && ! $post->trashed())
                            <p class="mb-1 text-xs font-medium text-ink/65">{{ $post->author?->name ?? 'A member' }} wrote:</p>
                            <x-ui.prose class="text-sm" :html="$post->body" />
                        @else
                            <p class="text-sm italic text-ink/65">This post has already been removed.</p>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <form method="POST" action="{{ route('admin.forum-reports.dismiss', $report) }}">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary">
                                <x-ui.icon name="check" class="h-4 w-4" /> Dismiss
                            </x-ui.button>
                        </form>
                        @if ($post && ! $post->trashed())
                            <form method="POST" action="{{ route('admin.forum-reports.remove', $post) }}" x-data
                                  @submit.prevent="if (await window.uprlConfirm({ title: 'Remove this post?', text: 'It will be hidden from the discussion and all its reports closed.', confirmText: 'Yes, remove' })) $el.submit()">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="danger">
                                    <x-ui.icon name="trash" class="h-4 w-4" /> Remove post
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <x-ui.empty-state icon="check-circle" title="Nothing to review"
                    description="Reported forum posts land here for a moderator to look at. The queue is clear." />
            @endforelse
        </div>

        {{ $reports->links() }}
    </div>
</x-app-layout>
