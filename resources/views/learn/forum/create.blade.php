@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\Lesson|null $lesson */
@endphp

<x-app-layout :title="'New discussion · '.$course->title">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ route('forum.index', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to forum
            </a>
            <h2 class="mt-3 font-display text-2xl font-semibold text-ink">Start a discussion</h2>
            <p class="text-sm text-ink/65">{{ $course->title }} · {{ $course->code }}</p>
        </div>

        <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
            @if ($lesson)
                <div class="mb-5 flex items-center gap-2 rounded-xl border border-crimson/20 bg-crimson/5 px-4 py-2.5 text-sm text-ink/75">
                    <x-ui.icon name="document-text" class="h-4 w-4 text-crimson" />
                    Discussing lesson: <strong class="font-semibold text-ink">{{ $lesson->title }}</strong>
                </div>
            @endif

            <form method="POST" action="{{ route('forum.store', $course) }}" class="space-y-5">
                @csrf
                @if ($lesson)
                    <input type="hidden" name="lesson_id" value="{{ $lesson->id }}">
                @endif

                <x-ui.field name="title" label="Title" required :value="old('title')"
                            placeholder="Ask a clear question, e.g. How does framing differ from priming?" />

                <x-ui.rich-editor name="body" label="Your message" :value="old('body')" height="240" profile="basic"
                                  placeholder="Give enough detail for someone to help — what you've tried, where you're stuck…" required />

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button variant="ghost" :href="route('forum.index', $course)">Cancel</x-ui.button>
                    <x-ui.button type="submit">
                        <x-ui.icon name="chat-group" class="h-4 w-4" /> Post discussion
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
