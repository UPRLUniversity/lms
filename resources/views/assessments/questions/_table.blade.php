@php
    use Illuminate\Support\Str;
    $filters ??= ['search' => '', 'type' => '', 'difficulty' => '', 'category' => ''];
    $isFiltered = collect($filters)->filter()->isNotEmpty();
@endphp

<div class="flex items-center justify-between gap-3 px-1 pb-3 text-sm text-ink/60">
    <p aria-live="polite">
        {{ $questions->total() }} {{ Str::plural('question', $questions->total()) }}
        @if ($isFiltered)<span class="text-ink/40">· filtered</span>@endif
    </p>
</div>

<x-ui.card :padding="false">
    @if ($questions->isEmpty())
        <div class="p-5">
            <x-ui.empty-state
                icon="clipboard"
                title="No questions yet"
                description="Author your first question — multiple choice, true/false, matching, essay and more — to start building assessments." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/50">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">Question</th>
                        <th scope="col" class="px-5 py-3 font-medium">Type</th>
                        <th scope="col" class="px-5 py-3 font-medium">Category</th>
                        <th scope="col" class="px-5 py-3 font-medium">Difficulty</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Points</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($questions as $question)
                        <tr class="hover:bg-surface/60">
                            <td class="px-5 py-3 max-w-md">
                                <a href="{{ route('questions.edit', [$course, $question]) }}"
                                   class="line-clamp-2 font-medium text-ink hover:text-crimson focus-ring rounded">
                                    {{ Str::limit(strip_tags($question->prompt), 110) }}
                                </a>
                                @if ($question->assessments_count > 0)
                                    <span class="mt-0.5 inline-flex items-center gap-1 text-xs text-ink/45">
                                        <x-ui.icon name="clipboard" class="h-3.5 w-3.5" />
                                        used in {{ $question->assessments_count }} {{ Str::plural('assessment', $question->assessments_count) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1.5 text-ink/70">
                                    <x-ui.icon :name="$question->type->icon()" class="h-4 w-4 text-ink/40" />
                                    {{ $question->type->shortLabel() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-ink/60">{{ $question->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <x-ui.badge :variant="$question->difficulty->badge()">{{ $question->difficulty->label() }}</x-ui.badge>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-ink/70">{{ rtrim(rtrim(number_format((float) $question->points, 2), '0'), '.') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-ui.button size="sm" variant="ghost" :href="route('questions.edit', [$course, $question])">
                                        <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('questions.duplicate', [$course, $question]) }}" data-ajax>
                                        @csrf
                                        <x-ui.button size="sm" variant="ghost" type="submit" title="Duplicate">
                                            <x-ui.icon name="clipboard-check" class="h-4 w-4" /><span class="sr-only">Duplicate</span>
                                        </x-ui.button>
                                    </form>
                                    <form method="POST" action="{{ route('questions.destroy', [$course, $question]) }}" data-ajax
                                          data-confirm="Delete this question?" data-confirm-action="Yes, delete">
                                        @csrf
                                        @method('delete')
                                        <x-ui.button size="sm" variant="ghost" type="submit" title="Delete"
                                                     class="text-ink/50 hover:text-crimson">
                                            <x-ui.icon name="trash" class="h-4 w-4" /><span class="sr-only">Delete</span>
                                        </x-ui.button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>

<div class="mt-4">
    {{ $questions->links('pagination.uprl') }}
</div>
