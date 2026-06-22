@php
    /** @var \App\Models\Assessment $assessment */
    /** @var \App\Models\Course $course */
    $canManage = $canManage ?? false;
@endphp

{{-- A pre/post-module assessment shown inline in the curriculum outline. Non-draggable
     (it sits outside the lesson reorder list); its position is fixed by placement. --}}
<div class="flex items-center gap-2 border-l-2 border-gold/40 bg-gold/5 px-3 py-2.5">
    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gold/15 text-gold-ink">
        <x-ui.icon name="clipboard" class="h-4 w-4" />
    </span>

    @if ($canManage)
        <a href="{{ route('assessments.edit', [$course, $assessment]) }}" class="min-w-0 flex-1 rounded focus-ring">
            <span class="block truncate text-sm font-medium text-ink">{{ $assessment->title }}</span>
            <span class="text-xs text-ink/50">{{ $assessment->placement->label() }} assessment · {{ $assessment->questionCount() }} {{ \Illuminate\Support\Str::plural('question', $assessment->questionCount()) }}</span>
        </a>
    @else
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-ink">{{ $assessment->title }}</span>
            <span class="text-xs text-ink/50">{{ $assessment->placement->label() }} assessment</span>
        </span>
    @endif

    <x-ui.badge :variant="$assessment->status->badge()">{{ $assessment->status->label() }}</x-ui.badge>
</div>
