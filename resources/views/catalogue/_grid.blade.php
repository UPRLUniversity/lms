@php use Illuminate\Support\Str; @endphp

<div class="flex items-center justify-between gap-3">
    <p class="text-sm text-ink/60" aria-live="polite">
        {{ $courses->total() }} {{ Str::plural('course', $courses->total()) }}
        @if ($filters['search'] !== '' || $filters['faculty'] !== '' || $filters['department'] !== '' || $filters['level'] !== '')
            <span class="text-ink/40">· filtered</span>
        @endif
    </p>
</div>

@if ($courses->isEmpty())
    <div class="mt-6">
        <x-ui.empty-state
            icon="book"
            title="No courses match your search"
            description="Try a broader search or clear the filters to see everything on offer." />
    </div>
@else
    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($courses as $course)
            <x-courses.catalogue-card :course="$course" />
        @endforeach
    </div>

    <div class="mt-10">
        {{ $courses->links('pagination.uprl') }}
    </div>
@endif
