@php
    use Illuminate\Support\Str;

    /** @var array{type: string, model: mixed} $item */
    /** @var \App\Models\Course $course */
    $canManage = $canManage ?? false;
    $model = $item['model'];
    $type = $item['type'];

    // Lessons, quizzes and assignments are equal siblings in one ladder now — the tint
    // is the only thing that distinguishes them, and every row drags the same way.
    $tone = match ($type) {
        'assessment' => ['icon' => 'clipboard', 'chip' => 'bg-gold/15 text-gold-ink', 'row' => 'bg-gold/5'],
        'assignment' => ['icon' => 'document-text', 'chip' => 'bg-crimson/10 text-crimson', 'row' => 'bg-crimson/5'],
        default => ['icon' => $model->type->icon(), 'chip' => 'bg-ink/5 text-ink/60', 'row' => ''],
    };

    $kindLabel = match ($type) {
        'assessment' => 'Quiz',
        'assignment' => 'Assignment',
        default => 'Lesson',
    };
@endphp

<li data-curriculum-item data-item-type="{{ $type }}" data-item-id="{{ $model->id }}"
    class="flex items-center gap-2 px-3 py-2.5 {{ $tone['row'] }} hover:bg-surface/50">
    @if ($canManage)
        {{-- The one reorder affordance: drag with a pointer, Alt+↑/↓ with a keyboard. --}}
        <button type="button" data-drag-item
                class="cursor-grab rounded-lg p-1.5 text-ink/30 hover:text-ink/60 focus-ring"
                aria-label="Reorder {{ $kindLabel }}: {{ $model->title }}. Press Alt with the up or down arrow to move it."
                title="Drag, or press Alt+↑ / Alt+↓">
            <x-ui.icon name="grip-vertical" class="h-4 w-4" />
        </button>
    @endif

    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tone['chip'] }}">
        <x-ui.icon :name="$tone['icon']" class="h-4 w-4" />
    </span>

    @if ($type === 'lesson')
        <button type="button" @if ($canManage) data-action="edit-lesson" data-lesson-id="{{ $model->id }}" @endif
                class="min-w-0 flex-1 rounded text-left focus-ring {{ $canManage ? 'cursor-pointer' : 'cursor-default' }}">
            <span class="block truncate text-sm font-medium text-ink">{{ $model->title }}</span>
            <span class="text-xs text-ink/50">{{ $model->type->label() }}</span>
        </button>

        @if ($model->is_free_preview)
            <x-ui.badge variant="success" class="hidden sm:inline-flex">Preview</x-ui.badge>
        @endif
        @if ($model->duration_minutes)
            <span class="shrink-0 text-xs text-ink/40">{{ $model->duration_minutes }}m</span>
        @endif

        @if ($canManage)
            <button type="button" data-action="delete-lesson" data-lesson-id="{{ $model->id }}"
                    class="rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Delete lesson">
                <x-ui.icon name="trash" class="h-4 w-4" />
            </button>
        @endif
    @elseif ($type === 'assessment')
        <a href="{{ route('assessments.edit', [$course, $model]) }}" class="min-w-0 flex-1 rounded focus-ring">
            <span class="block truncate text-sm font-medium text-ink">{{ $model->title }}</span>
            <span class="text-xs text-ink/50">
                Quiz · {{ $model->questionCount() }} {{ Str::plural('question', $model->questionCount()) }}
                @unless ($model->counts_toward_grade) · not graded @endunless
            </span>
        </a>
        <x-ui.badge :variant="$model->status->badge()">{{ $model->status->label() }}</x-ui.badge>
    @else
        <a href="{{ route('assignments.edit', [$course, $model]) }}" class="min-w-0 flex-1 rounded focus-ring">
            <span class="block truncate text-sm font-medium text-ink">{{ $model->title }}</span>
            <span class="text-xs text-ink/50">
                Assignment · {{ $model->type->label() }}
                @if ($model->due_at) · due {{ $model->due_at->isoFormat('D MMM') }} @endif
                @unless ($model->counts_toward_grade) · not graded @endunless
            </span>
        </a>
        <x-ui.badge :variant="$model->status->badge()">{{ $model->status->label() }}</x-ui.badge>
    @endif
</li>
