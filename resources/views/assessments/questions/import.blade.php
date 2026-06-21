@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Import questions">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('questions.index', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to question bank
        </a>
        <h2 class="mt-2 font-display text-2xl font-semibold text-ink">Import questions</h2>
        <p class="mt-1 text-sm text-ink/60">Copy questions from another course you teach into this bank.</p>

        @if ($sourceCourses->isEmpty())
            <div class="mt-6">
                <x-ui.empty-state icon="download" title="Nothing to import yet"
                    description="None of your other courses have questions to copy from." />
            </div>
        @else
            {{-- Source picker --}}
            <form method="GET" action="{{ route('questions.import.form', $course) }}" class="mt-6 flex items-end gap-3">
                <div>
                    <label for="source" class="block text-sm font-medium text-ink">From course</label>
                    <select id="source" name="source" onchange="this.form.submit()"
                            class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @foreach ($sourceCourses as $sc)
                            <option value="{{ $sc->id }}" @selected($source && $source->id === $sc->id)>{{ $sc->title }} ({{ $sc->questions_count }})</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <form method="POST" action="{{ route('questions.import', $course) }}" class="mt-5"
                  x-data="{ all: false }">
                @csrf
                <input type="hidden" name="source_course_id" value="{{ $source?->id }}">

                <x-ui.card :padding="false">
                    <div class="flex items-center gap-2 border-b border-line px-4 py-3">
                        <input id="all" type="checkbox" x-model="all"
                               @change="$root.querySelectorAll('input[name=\'question_ids[]\']').forEach(c => c.checked = all)"
                               class="rounded border-line text-crimson focus:ring-crimson">
                        <label for="all" class="text-sm font-medium text-ink">Select all</label>
                    </div>
                    <ul class="divide-y divide-line">
                        @forelse ($questions as $q)
                            <li class="flex items-start gap-3 px-4 py-3">
                                <input type="checkbox" name="question_ids[]" value="{{ $q->id }}"
                                       class="mt-1 rounded border-line text-crimson focus:ring-crimson">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-ink">{{ Str::limit(strip_tags($q->prompt), 120) }}</p>
                                    <p class="mt-0.5 text-xs text-ink/50">{{ $q->type->shortLabel() }} · {{ $q->difficulty->label() }} · {{ $q->category?->name ?? 'Uncategorised' }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center text-sm text-ink/40">This course has no questions.</li>
                        @endforelse
                    </ul>
                </x-ui.card>

                <x-input-error :messages="$errors->get('question_ids')" class="mt-2" />

                <div class="mt-4 flex justify-end">
                    <x-ui.button type="submit"><x-ui.icon name="download" class="h-4 w-4" /> Import selected</x-ui.button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
