@php
    use App\Enums\SubmissionStatus;

    /** @var \Illuminate\Pagination\LengthAwarePaginator $queue */
@endphp

<x-app-layout title="Assignment grading">
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Grading</h2>
            <p class="mt-1 text-sm text-ink/60">Assignment submissions across your courses.</p>
        </div>

        {{-- Queue switcher: assessment attempts ↔ assignment submissions --}}
        <div class="flex gap-1 rounded-xl border border-line bg-card p-1 shadow-sm w-fit" role="tablist" aria-label="Grading queues">
            <a href="{{ route('grading.index') }}"
               class="rounded-lg px-4 py-2 text-sm font-medium text-ink/60 transition hover:bg-ink/5 focus-ring">Assessments</a>
            <a href="{{ route('grading.assignments.index') }}" aria-current="page"
               class="rounded-lg bg-crimson/10 px-4 py-2 text-sm font-medium text-crimson focus-ring">Assignments</a>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="f-course" class="block text-xs font-medium text-ink/60">Course</label>
                <select id="f-course" name="course" onchange="this.form.submit()"
                        class="mt-1 block w-52 rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->slug }}" @selected(request('course') === $course->slug)>{{ $course->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-assignment" class="block text-xs font-medium text-ink/60">Assignment</label>
                <select id="f-assignment" name="assignment" onchange="this.form.submit()"
                        class="mt-1 block w-52 rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All assignments</option>
                    @foreach ($assignments as $assignmentOption)
                        <option value="{{ $assignmentOption->id }}" @selected((int) request('assignment') === $assignmentOption->id)>{{ $assignmentOption->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-status" class="block text-xs font-medium text-ink/60">Status</label>
                <select id="f-status" name="status" onchange="this.form.submit()"
                        class="mt-1 block w-56 rounded-xl border-line bg-card text-sm text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    @foreach (SubmissionStatus::cases() as $option)
                        <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                    <option value="all" @selected($status === 'all')>All statuses</option>
                </select>
            </div>
        </form>

        {{-- A marks sheet is always ABOUT one assignment, so this only appears once the
             filter has narrowed to one — there is nothing sensible to upload against
             "all assignments". --}}
        @php
            $selectedAssignment = request('assignment')
                ? $assignments->firstWhere('id', (int) request('assignment'))
                : null;
        @endphp
        @if ($selectedAssignment && auth()->user()->can('grade', $selectedAssignment))
            <div class="flex items-center justify-between gap-3 rounded-xl border border-line bg-card px-4 py-3">
                <p class="text-sm text-ink/70">
                    Marked <span class="font-medium text-ink">{{ $selectedAssignment->title }}</span> offline?
                </p>
                <x-ui.button size="sm" variant="secondary"
                             :href="route('admin.imports.create', ['import' => 'grades', 'scopeId' => $selectedAssignment->id])">
                    <x-ui.icon name="document-text" class="h-4 w-4" /> Upload marks
                </x-ui.button>
            </div>
        @endif

        @if ($queue->isEmpty())
            <x-ui.empty-state icon="check-circle" title="All caught up"
                description="No submissions match these filters — nothing is waiting on you here." />
        @else
            <x-ui.card :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($queue as $submission)
                        <li class="flex items-center justify-between gap-3 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium text-ink">{{ $submission->assignment->title }}</p>
                                    @if ($submission->is_late)
                                        <x-ui.badge variant="crimson">Late</x-ui.badge>
                                    @endif
                                    <x-ui.badge :variant="$submission->status->badge()">{{ $submission->status->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-0.5 text-sm text-ink/55">
                                    {{ $submission->user->name }} · {{ $submission->assignment->course->title }}
                                    · v{{ $submission->version }}
                                    · {{ $submission->media_count }} {{ \Illuminate\Support\Str::plural('file', $submission->media_count) }}
                                    · submitted {{ $submission->submitted_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-ui.button size="sm" :variant="$submission->awaitingGrading() ? 'primary' : 'secondary'" :href="route('grading.assignments.show', $submission)">
                                <x-ui.icon name="pencil" class="h-4 w-4" /> {{ $submission->awaitingGrading() ? 'Grade' : 'Open' }}
                            </x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <div>{{ $queue->links('pagination.uprl') }}</div>
        @endif
    </div>
</x-app-layout>
