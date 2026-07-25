@php
    /** @var \Illuminate\Support\Collection $conversations */
    /** @var \Illuminate\Support\Collection $contacts */
    /** @var bool $canCreateGroup */
    use App\Enums\ConversationType;
    $me = auth()->user();
@endphp

<x-app-layout title="New message">
    <div class="mx-auto max-w-6xl">
        <div class="grid gap-4 lg:grid-cols-[20rem_1fr] lg:gap-6">
            {{-- Conversation list (unchanged) --}}
            <aside class="hidden flex-col rounded-2xl border border-line bg-card shadow-sm lg:flex lg:h-[calc(100vh-8rem)]" aria-label="Conversations">
                <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <h2 class="font-display text-lg font-semibold text-ink">Messages</h2>
                </div>
                <div class="flex-1 overflow-y-auto p-2">
                    @foreach ($conversations as $conversation)
                        <a href="{{ route('messages.show', $conversation) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-ink/5 focus-ring">
                            @if ($conversation->isGroup())
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold/15 text-gold-ink"><x-ui.icon name="chat-group" class="h-4 w-4" /></span>
                            @else
                                <x-ui.avatar :user="$conversation->otherParticipant($me)" size="sm" />
                            @endif
                            <span class="truncate text-sm font-medium text-ink/90">{{ $conversation->titleFor($me) }}</span>
                        </a>
                    @endforeach
                </div>
            </aside>

            {{-- Composer --}}
            <section class="rounded-2xl border border-line bg-card shadow-sm">
                <div class="flex items-center gap-3 border-b border-line px-4 py-3">
                    <a href="{{ route('messages.index') }}" class="rounded-lg p-1.5 text-ink/50 hover:bg-ink/5 hover:text-ink focus-ring" aria-label="Back">
                        <x-ui.icon name="arrow-left" class="h-5 w-5" />
                    </a>
                    <h2 class="font-display text-lg font-semibold text-ink">New message</h2>
                </div>

                @if ($contacts->isEmpty())
                    <div class="p-8">
                        <x-ui.empty-state icon="users" title="No one to message yet"
                            description="Once you're enrolled on a course (or teaching one), your instructors and coursemates appear here." class="border-0 bg-transparent shadow-none" />
                    </div>
                @else
                    <form method="POST" action="{{ route('messages.store') }}" class="space-y-5 p-5"
                          x-data="{ mode: '{{ old('type', ConversationType::Direct->value) }}' }">
                        @csrf

                        {{-- Direct / Group toggle (staff only) --}}
                        @if ($canCreateGroup)
                            <div class="inline-flex rounded-xl border border-line bg-surface p-1" role="tablist" aria-label="Conversation type">
                                <button type="button" @click="mode = '{{ ConversationType::Direct->value }}'"
                                        :class="mode === '{{ ConversationType::Direct->value }}' ? 'bg-card text-crimson shadow-sm' : 'text-ink/60'"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium focus-ring">Direct</button>
                                <button type="button" @click="mode = '{{ ConversationType::Group->value }}'"
                                        :class="mode === '{{ ConversationType::Group->value }}' ? 'bg-card text-crimson shadow-sm' : 'text-ink/60'"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium focus-ring">Group</button>
                            </div>
                        @endif
                        <input type="hidden" name="type" :value="mode">

                        {{-- Direct: one recipient --}}
                        <div x-show="mode === '{{ ConversationType::Direct->value }}'" class="space-y-1.5">
                            <label for="recipient_id" class="block text-sm font-medium text-ink">To <span class="text-crimson">*</span></label>
                            <select name="recipient_id" id="recipient_id"
                                    class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <option value="">Choose a person…</option>
                                @foreach ($contacts as $contact)
                                    <option value="{{ $contact->id }}" @selected(old('recipient_id') == $contact->id)>{{ $contact->name }}</option>
                                @endforeach
                            </select>
                            @error('recipient_id') <p class="text-sm text-crimson">{{ $message }}</p> @enderror
                        </div>

                        {{-- Group: subject + participants --}}
                        @if ($canCreateGroup)
                            <div x-show="mode === '{{ ConversationType::Group->value }}'" x-cloak class="space-y-4">
                                <x-ui.field name="subject" label="Subject" :value="old('subject')" placeholder="e.g. Group project — Team B" />
                                @error('subject') <p class="text-sm text-crimson">{{ $message }}</p> @enderror

                                <fieldset class="space-y-2">
                                    <legend class="text-sm font-medium text-ink">Participants</legend>
                                    <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
                                        @foreach ($contacts as $contact)
                                            <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-surface">
                                                <input type="checkbox" name="participant_ids[]" value="{{ $contact->id }}"
                                                       @checked(collect(old('participant_ids'))->contains($contact->id))
                                                       class="rounded border-line text-crimson focus:ring-crimson">
                                                <x-ui.avatar :user="$contact" size="xs" />
                                                <span class="text-sm text-ink">{{ $contact->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('participant_ids') <p class="text-sm text-crimson">{{ $message }}</p> @enderror
                                </fieldset>
                            </div>
                        @endif

                        <x-ui.rich-editor name="body" label="Message" :value="old('body')" height="180" profile="basic"
                                          placeholder="Write your message…" required />

                        <div class="flex justify-end gap-3">
                            <x-ui.button variant="ghost" :href="route('messages.index')">Cancel</x-ui.button>
                            <x-ui.button type="submit"><x-ui.icon name="chat" class="h-4 w-4" /> Send message</x-ui.button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
