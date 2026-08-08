@php
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $threads */
    /** @var string $filter */

    use App\Enums\Role;

    $user = auth()->user();
    $isStudent = $user->hasRole(Role::Student->value);
    $leadInstructor = $course->leadInstructor();
@endphp

<x-app-layout :title="'Forum · '.$course->title">
    <div class="mx-auto max-w-4xl space-y-6">
        {{-- Header --}}
        <div>
            <a href="{{ route('learn.resume', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
            </a>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                        <x-ui.icon name="chat-group" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="font-display text-2xl font-semibold text-ink">Discussion forum</h2>
                        <p class="text-sm text-ink/65">{{ $course->title }} · {{ $course->code }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($isStudent && $leadInstructor)
                        <form method="POST" action="{{ route('messages.start', $leadInstructor) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm">
                                <x-ui.icon name="chat" class="h-4 w-4" /> Message instructor
                            </x-ui.button>
                        </form>
                    @endif

                    @if ($canModerate)
                        <x-ui.button variant="secondary" size="sm" x-data @click="$dispatch('open-modal', 'message-all')">
                            <x-ui.icon name="megaphone" class="h-4 w-4" /> Message all enrolled
                        </x-ui.button>
                    @endif

                    @if ($canParticipate)
                        <x-ui.button size="sm" :href="route('forum.create', $course)">
                            <x-ui.icon name="plus" class="h-4 w-4" /> New discussion
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Lesson-scope banner --}}
        @isset($lesson)
            @if ($lesson)
                <div class="flex items-center justify-between gap-3 rounded-xl border border-crimson/20 bg-crimson/5 px-4 py-3 text-sm">
                    <span class="flex items-center gap-2 text-ink/75">
                        <x-ui.icon name="document-text" class="h-4 w-4 text-crimson" />
                        Showing discussions about <strong class="font-semibold text-ink">{{ $lesson->title }}</strong>
                    </span>
                    <a href="{{ route('forum.index', $course) }}" class="font-medium text-crimson hover:text-crimson-dark focus-ring rounded">Show all</a>
                </div>
            @endif
        @endisset

        {{-- Filter tabs --}}
        <div class="flex items-center gap-1 border-b border-line">
            @php
                $tabBase = 'relative -mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium focus-ring rounded-t';
                $tabs = ['all' => 'All discussions', 'unanswered' => 'Unanswered'];
            @endphp
            @foreach ($tabs as $key => $label)
                <a href="{{ route('forum.index', array_merge([$course], $key === 'all' ? [] : ['filter' => $key], request('lesson') ? ['lesson' => request('lesson')] : [])) }}"
                   @class([
                       $tabBase,
                       'border-crimson text-crimson' => $filter === $key,
                       'border-transparent text-ink/65 hover:text-ink hover:border-line' => $filter !== $key,
                   ])
                   @if ($filter === $key) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        {{-- Thread list --}}
        <div class="space-y-3">
            @forelse ($threads as $thread)
                <a href="{{ route('forum.show', [$course, $thread]) }}"
                   class="group block rounded-2xl border border-line bg-card p-5 shadow-sm transition hover:border-crimson/40 hover:shadow focus-ring">
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :user="$thread->author" size="sm" class="mt-0.5" />
                        <div class="min-w-0 flex-1">
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
                            </div>
                            <h3 class="mt-1 truncate font-display text-lg font-semibold text-ink group-hover:text-crimson">
                                {{ $thread->title }}
                            </h3>
                            <p class="mt-0.5 text-xs text-ink/65">
                                {{ $thread->author?->name ?? 'A member' }}
                                @if ($thread->lesson)
                                    · on <span class="text-ink/70">{{ $thread->lesson->title }}</span>
                                @endif
                                · {{ $thread->last_activity_at?->diffForHumans() ?? $thread->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="inline-flex items-center gap-1 text-sm font-medium text-ink/65">
                                <x-ui.icon name="chat" class="h-4 w-4" /> {{ $thread->replyCount() }}
                            </span>
                            <p class="text-[11px] text-ink/65">{{ \Illuminate\Support\Str::plural('reply', $thread->replyCount()) }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <x-ui.empty-state icon="chat-group"
                    title="{{ $filter === 'unanswered' ? 'No unanswered discussions' : 'No discussions yet' }}"
                    description="{{ $filter === 'unanswered' ? 'Every question here has an accepted answer — nice work.' : 'Start the conversation — ask a question or share something with your coursemates.' }}">
                    @if ($canParticipate && $filter !== 'unanswered')
                        <x-slot name="action">
                            <x-ui.button :href="route('forum.create', $course)">
                                <x-ui.icon name="plus" class="h-4 w-4" /> New discussion
                            </x-ui.button>
                        </x-slot>
                    @endif
                </x-ui.empty-state>
            @endforelse
        </div>

        {{ $threads->links() }}
    </div>

    {{-- Message-all-enrolled modal (instructors/admins) --}}
    @if ($canModerate)
        <x-ui.modal name="message-all" title="Message everyone enrolled" maxWidth="lg">
            <form method="POST" action="{{ route('messages.course', $course) }}" class="space-y-4">
                @csrf
                <p class="text-sm text-ink/70">
                    Sends to the course's group conversation — every active and completed student, in one thread.
                </p>
                <x-ui.field name="subject" label="Subject" required :value="old('subject')"
                            placeholder="e.g. Live session moved to Thursday" />
                <x-ui.rich-editor name="body" label="Message" :value="old('body')" height="180" profile="basic"
                                  placeholder="Write your message…" required />
                <div class="flex justify-end gap-3 pt-1">
                    <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', 'message-all')">Cancel</x-ui.button>
                    <x-ui.button type="submit">
                        <x-ui.icon name="chat" class="h-4 w-4" /> Send to all
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</x-app-layout>
