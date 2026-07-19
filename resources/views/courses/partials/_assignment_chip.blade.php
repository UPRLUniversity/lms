@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Assignment $assignment */
    /** @var \App\Models\Course $course */
    $canManage = $canManage ?? false;
@endphp

{{-- An assignment shown inline in the curriculum outline (after the module's lessons,
     or in the standalone list). Crimson-tinted to distinguish it from gold assessments. --}}
<div class="flex items-center gap-2 border-l-2 border-crimson/30 bg-crimson/5 px-3 py-2.5">
    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-crimson/10 text-crimson">
        <x-ui.icon name="document-text" class="h-4 w-4" />
    </span>

    @if ($canManage)
        <a href="{{ route('assignments.edit', [$course, $assignment]) }}" class="min-w-0 flex-1 rounded focus-ring">
            <span class="block truncate text-sm font-medium text-ink">{{ $assignment->title }}</span>
            <span class="text-xs text-ink/50">
                Assignment · {{ $assignment->type->label() }}
                @if ($assignment->due_at) · due {{ $assignment->due_at->isoFormat('D MMM') }} @endif
            </span>
        </a>
    @else
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-ink">{{ $assignment->title }}</span>
            <span class="text-xs text-ink/50">Assignment</span>
        </span>
    @endif

    <x-ui.badge :variant="$assignment->status->badge()">{{ $assignment->status->label() }}</x-ui.badge>
</div>
