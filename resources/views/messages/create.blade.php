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
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold/10 text-gold-ink"><x-ui.icon name="chat-group" class="h-4 w-4" /></span>
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
                    <a href="{{ route('messages.index') }}" class="rounded-lg p-1.5 text-ink/65 hover:bg-ink/5 hover:text-ink focus-ring" aria-label="Back">
                        <x-ui.icon name="arrow-left" class="h-5 w-5" />
                    </a>
                    <h2 class="font-display text-lg font-semibold text-ink">New message</h2>
                </div>

                @if ($contacts->isEmpty())
                    <div class="p-8">
                        {{-- Staff reach the whole directory, so an empty list means there is
                             genuinely nobody else — not "you haven't enrolled yet". --}}
                        <x-ui.empty-state icon="users" title="No one to message yet"
                            :description="$canCreateGroup
                                ? 'There are no other active accounts yet. Once people are registered or invited, they appear here.'
                                : 'Once you\'re enrolled on a course (or teaching one), your instructors and coursemates appear here.'"
                            class="border-0 bg-transparent shadow-none" />
                    </div>
                @else
                    <form method="POST" action="{{ route('messages.store') }}" class="space-y-5 p-5"
                          x-data="{ mode: '{{ old('type', ConversationType::Direct->value) }}' }">
                        @csrf

                        {{-- Direct / Group toggle (staff only) --}}
                        @if ($canCreateGroup)
                            <div class="inline-flex rounded-xl border border-line bg-surface p-1" role="tablist" aria-label="Conversation type">
                                <button type="button" @click="mode = '{{ ConversationType::Direct->value }}'"
                                        :class="mode === '{{ ConversationType::Direct->value }}' ? 'bg-card text-crimson shadow-sm' : 'text-ink/65'"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium focus-ring">Direct</button>
                                <button type="button" @click="mode = '{{ ConversationType::Group->value }}'"
                                        :class="mode === '{{ ConversationType::Group->value }}' ? 'bg-card text-crimson shadow-sm' : 'text-ink/65'"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium focus-ring">Group</button>
                            </div>
                        @endif
                        <input type="hidden" name="type" :value="mode">

                        {{-- Direct: one recipient. A native <select> while the list is
                             small enough to render; a searching combobox once it isn't
                             (admins can reach the whole directory). --}}
                        <div x-show="mode === '{{ ConversationType::Direct->value }}'" class="space-y-1.5">
                            @if ($contactsAreCapped)
                                <label for="recipient_search" class="block text-sm font-medium text-ink">To <span class="text-crimson">*</span></label>
                                <div x-data="contactPicker({ searchUrl: '{{ route('messages.contacts') }}' })" class="relative">
                                    <input type="hidden" name="recipient_id" :value="recipientId">

                                    {{-- Chosen: show who, with a way back to searching. --}}
                                    <div x-show="chosen.length" x-cloak class="flex items-center justify-between gap-2 rounded-xl border border-line bg-surface px-3.5 py-2.5">
                                        <span class="truncate text-sm font-medium text-ink" x-text="label"></span>
                                        <button type="button" @click="clear()" class="rounded-lg p-1 text-ink/65 hover:text-crimson focus-ring" aria-label="Choose someone else">
                                            <x-ui.icon name="x" class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <div x-show="!chosen.length">
                                        <input type="text" id="recipient_search" x-ref="search" x-model="term"
                                               @input="onInput()" @focus="open = true" @keydown="onKeydown($event)"
                                               @click.outside="open = false"
                                               autocomplete="off" role="combobox" aria-expanded="false"
                                               :aria-expanded="open.toString()" aria-controls="recipient_results"
                                               placeholder="Search by name or email…"
                                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">

                                        <ul x-show="open" x-cloak id="recipient_results" role="listbox"
                                            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-line bg-card py-1 shadow-lg">
                                            <template x-if="loading && !results.length">
                                                <li class="px-3.5 py-2.5"><x-ui.skeleton class="h-4 w-2/3" /></li>
                                            </template>
                                            <template x-if="!loading && !results.length">
                                                <li class="px-3.5 py-2.5 text-sm text-ink/65">No one matches “<span x-text="term"></span>”.</li>
                                            </template>
                                            <template x-for="(person, i) in results" :key="person.id">
                                                <li role="option" :aria-selected="(i === activeIndex).toString()">
                                                    <button type="button" @click="choose(person)" @mouseenter="activeIndex = i"
                                                            :class="i === activeIndex ? 'bg-crimson/5' : ''"
                                                            class="block w-full px-3.5 py-2 text-left focus-ring">
                                                        <span class="block truncate text-sm font-medium text-ink" x-text="person.name"></span>
                                                        <span class="block truncate text-xs text-ink/65" x-text="person.email"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            @else
                                <label for="recipient_id" class="block text-sm font-medium text-ink">To <span class="text-crimson">*</span></label>
                                <select name="recipient_id" id="recipient_id"
                                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                    <option value="">Choose a person…</option>
                                    @foreach ($contacts as $contact)
                                        <option value="{{ $contact->id }}" @selected(old('recipient_id') == $contact->id)>{{ $contact->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('recipient_id') <p class="text-sm text-crimson">{{ $message }}</p> @enderror
                        </div>

                        {{-- Group: subject + participants --}}
                        @if ($canCreateGroup)
                            <div x-show="mode === '{{ ConversationType::Group->value }}'" x-cloak class="space-y-4">
                                <x-ui.field name="subject" label="Subject" :value="old('subject')" placeholder="e.g. Group project — Team B" />
                                @error('subject') <p class="text-sm text-crimson">{{ $message }}</p> @enderror

                                <fieldset class="space-y-2">
                                    <legend class="text-sm font-medium text-ink">Participants</legend>
                                    @if ($contactsAreCapped)
                                        {{-- Too many to list: search and collect. --}}
                                        <div x-data="contactPicker({ searchUrl: '{{ route('messages.contacts') }}', multiple: true })" class="space-y-2">
                                            <template x-for="person in chosen" :key="person.id">
                                                <input type="hidden" name="participant_ids[]" :value="person.id">
                                            </template>

                                            <div x-show="chosen.length" x-cloak class="flex flex-wrap gap-1.5">
                                                <template x-for="person in chosen" :key="person.id">
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-crimson/10 py-1 pl-3 pr-1.5 text-sm text-ink">
                                                        <span x-text="person.name"></span>
                                                        <button type="button" @click="remove(person)" class="rounded-full p-0.5 text-ink/65 hover:text-crimson focus-ring"
                                                                :aria-label="`Remove ${person.name}`">
                                                            <x-ui.icon name="x" class="h-3.5 w-3.5" />
                                                        </button>
                                                    </span>
                                                </template>
                                            </div>

                                            <input type="text" x-model="term" @input="onInput()" autocomplete="off"
                                                   placeholder="Search by name or email…" aria-label="Search for participants"
                                                   class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">

                                            <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
                                                <template x-if="loading && !results.length">
                                                    <div class="px-2 py-1.5"><x-ui.skeleton class="h-4 w-2/3" /></div>
                                                </template>
                                                <template x-if="!loading && !results.length">
                                                    <p class="px-2 py-1.5 text-sm text-ink/65">No one matches that search.</p>
                                                </template>
                                                <template x-for="person in results" :key="person.id">
                                                    <button type="button" @click="choose(person)"
                                                            class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-surface focus-ring">
                                                        <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border border-line"
                                                              :class="isChosen(person) ? 'border-crimson bg-crimson text-white' : ''">
                                                            <span x-show="isChosen(person)" class="text-[10px] leading-none">✓</span>
                                                        </span>
                                                        <span class="min-w-0">
                                                            <span class="block truncate text-sm text-ink" x-text="person.name"></span>
                                                            <span class="block truncate text-xs text-ink/65" x-text="person.email"></span>
                                                        </span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    @else
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
                                    @endif
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
