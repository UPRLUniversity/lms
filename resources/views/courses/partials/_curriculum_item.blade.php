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
        'assessment' => ['icon' => 'clipboard', 'chip' => 'bg-gold/10 text-gold-ink', 'row' => 'bg-gold/5'],
        'assignment' => ['icon' => 'document-text', 'chip' => 'bg-crimson/10 text-crimson', 'row' => 'bg-crimson/5'],
        default => ['icon' => $model->type->icon(), 'chip' => 'bg-ink/5 text-ink/65', 'row' => ''],
    };

    $kindLabel = match ($type) {
        'assessment' => 'Quiz',
        'assignment' => 'Assignment',
        default => 'Lesson',
    };

    // Withdrawn from the course but keeping its student work (Section 16). Dimmed rather
    // than removed, so staff can still see it and put it back.
    $isHidden = $model->isHidden();
@endphp

<li data-curriculum-item data-item-type="{{ $type }}" data-item-id="{{ $model->id }}"
    class="flex items-center gap-2 px-3 py-2.5 {{ $isHidden ? 'bg-ink/[0.03]' : $tone['row'] }} hover:bg-surface/50">
    @if ($canManage)
        {{-- The one reorder affordance: drag with a pointer, Alt+↑/↓ with a keyboard. --}}
        <button type="button" data-drag-item
                class="cursor-grab rounded-lg p-1.5 text-ink/65 hover:text-ink/65 focus-ring"
                aria-label="Reorder {{ $kindLabel }}: {{ $model->title }}. Press Alt with the up or down arrow to move it."
                title="Drag, or press Alt+↑ / Alt+↓">
            <x-ui.icon name="grip-vertical" class="h-4 w-4" />
        </button>
    @endif

    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $isHidden ? 'bg-ink/5 text-ink/65' : $tone['chip'] }}">
        <x-ui.icon :name="$isHidden ? 'eye-off' : $tone['icon']" class="h-4 w-4" />
    </span>

    @if ($type === 'lesson')
        <button type="button" @if ($canManage) data-action="edit-lesson" data-lesson-id="{{ $model->id }}" @endif
                class="min-w-0 flex-1 rounded text-left focus-ring {{ $canManage ? 'cursor-pointer' : 'cursor-default' }}">
            <span class="block truncate text-sm font-medium text-ink">{{ $model->title }}</span>
            <span class="text-xs text-ink/65">{{ $model->type->label() }}</span>
        </button>

        @if ($isHidden)
            <x-ui.badge variant="neutral" class="hidden sm:inline-flex">Hidden</x-ui.badge>
        @elseif ($model->is_free_preview)
            <x-ui.badge variant="success" class="hidden sm:inline-flex">Preview</x-ui.badge>
        @endif
        @if ($model->duration_minutes)
            <span class="shrink-0 text-xs text-ink/65">{{ $model->duration_minutes }}m</span>
        @endif

        @if ($canManage)
            @include('courses.partials._curriculum_visibility_toggle', ['type' => 'lesson', 'model' => $model, 'isHidden' => $isHidden])

            <button type="button" data-action="delete-lesson" data-lesson-id="{{ $model->id }}"
                    class="rounded-lg p-1.5 text-ink/65 hover:text-crimson focus-ring" aria-label="Delete lesson">
                <x-ui.icon name="trash" class="h-4 w-4" />
            </button>
        @endif
    @elseif ($type === 'assessment')
        <a href="{{ route('assessments.edit', [$course, $model]) }}" class="min-w-0 flex-1 rounded focus-ring">
            <span class="block truncate text-sm font-medium text-ink">{{ $model->title }}</span>
            <span class="text-xs text-ink/65">
                Quiz · {{ $model->questionCount() }} {{ Str::plural('question', $model->questionCount()) }}
                @unless ($model->counts_toward_grade) · not graded @endunless
            </span>
        </a>
        @if ($isHidden)
            <x-ui.badge variant="neutral">Hidden</x-ui.badge>
        @else
            <x-ui.badge :variant="$model->status->badge()">{{ $model->status->label() }}</x-ui.badge>
        @endif
        @if ($canManage)
            @include('courses.partials._curriculum_visibility_toggle', ['type' => 'assessment', 'model' => $model, 'isHidden' => $isHidden])
        @endif
    @else
        <a href="{{ route('assignments.edit', [$course, $model]) }}" class="min-w-0 flex-1 rounded focus-ring">
            <span class="block truncate text-sm font-medium text-ink">{{ $model->title }}</span>
            <span class="text-xs text-ink/65">
                Assignment · {{ $model->type->label() }}
                @if ($model->due_at) · due {{ $model->due_at->isoFormat('D MMM') }} @endif
                @unless ($model->counts_toward_grade) · not graded @endunless
            </span>
        </a>
        @if ($isHidden)
            <x-ui.badge variant="neutral">Hidden</x-ui.badge>
        @else
            <x-ui.badge :variant="$model->status->badge()">{{ $model->status->label() }}</x-ui.badge>
        @endif
        @if ($canManage)
            @include('courses.partials._curriculum_visibility_toggle', ['type' => 'assignment', 'model' => $model, 'isHidden' => $isHidden])
        @endif
    @endif
</li>
