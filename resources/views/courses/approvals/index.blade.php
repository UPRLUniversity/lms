<x-app-layout title="Approvals">
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Header --}}
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Enrolment approvals</h2>
            <p class="mt-1 text-ink/70">Review students requesting a place on your approval courses.</p>
        </div>

        @if ($pending->isEmpty())
            <x-ui.empty-state
                icon="check"
                title="You're all caught up"
                description="There are no pending enrolment requests right now." />
        @else
            {{-- One form drives both bulk-approve (default action) and the per-row
                 approve/reject buttons (via formaction). --}}
            <form method="POST" action="{{ route('enrollments.bulk-approve') }}"
                  x-data="{ selected: [] }">
                @csrf

                {{-- Bulk action bar --}}
                <div class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-line bg-card px-4 py-3 shadow-sm">
                    <label class="inline-flex items-center gap-2 text-sm text-ink/70">
                        <input type="checkbox"
                               @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('[name=\'ids[]\']')).map(c => c.value) : []"
                               :checked="selected.length > 0 && selected.length === document.querySelectorAll('[name=\'ids[]\']').length"
                               class="rounded border-line text-crimson focus:ring-crimson">
                        Select all
                    </label>
                    <x-ui.button type="submit" size="sm" x-bind:disabled="selected.length === 0"
                                 ::class="selected.length === 0 && 'opacity-50 pointer-events-none'">
                        <x-ui.icon name="check" class="h-4 w-4" stroke-width="2.5" />
                        Approve <span x-text="selected.length"></span> selected
                    </x-ui.button>
                </div>

                <div class="space-y-3">
                    @foreach ($pending as $request)
                        <div class="flex flex-wrap items-center gap-4 rounded-xl border border-line bg-card p-4 shadow-sm">
                            <input type="checkbox" name="ids[]" value="{{ $request->id }}"
                                   x-model="selected"
                                   class="rounded border-line text-crimson focus:ring-crimson"
                                   aria-label="Select {{ $request->user->name }}">

                            <x-ui.avatar :user="$request->user" size="md" />

                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">{{ $request->user->name }}</p>
                                <p class="truncate text-sm text-ink/60">{{ $request->user->email }}</p>
                                <p class="mt-0.5 text-xs text-ink/50">
                                    {{ $request->course->code }} · {{ $request->course->title }}
                                    · requested {{ $request->enrolled_at?->diffForHumans() }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-ui.button type="submit" size="sm" variant="secondary"
                                             formaction="{{ route('enrollments.approve', $request) }}" formmethod="post">
                                    <x-ui.icon name="check" class="h-4 w-4" /> Approve
                                </x-ui.button>
                                <button type="submit"
                                        formaction="{{ route('enrollments.reject', $request) }}" formmethod="post"
                                        data-decline-name="{{ $request->user->name }}"
                                        x-on:click.prevent="if (await window.uprlConfirm({ title: 'Decline ' + $el.dataset.declineName + '\'s request?', confirmText: 'Yes, decline' })) { $el.form.action = $el.getAttribute('formaction'); $el.form.submit(); }"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-crimson bg-transparent px-3 py-1.5 text-sm font-medium text-crimson transition-colors hover:bg-crimson hover:text-white focus-ring">
                                    Decline
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </form>

            <div>
                {{ $pending->links('pagination.uprl') }}
            </div>
        @endif
    </div>
</x-app-layout>
