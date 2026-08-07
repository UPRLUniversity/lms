@php
    use App\Enums\EnrollmentStatus;
    use Illuminate\Support\Str;

    /** @var \App\Models\Course $course */

    // Who is actually affected by an edit made right now. Counted here rather than in the
    // controller so every builder tab that includes this strip gets it without wiring.
    $counts = $course->enrollments()
        ->selectRaw('status, count(*) as total')
        ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
        ->groupBy('status')
        ->pluck('total', 'status');

    $taking = (int) $counts->get(EnrollmentStatus::Active->value, 0);
    $finished = (int) $counts->get(EnrollmentStatus::Completed->value, 0);
@endphp

@if ($taking > 0 || $finished > 0)
    {{--
        The blast radius, stated before the instructor edits anything. Informational, not a
        warning: editing a live course is normal and expected — the point is that it should
        never be a surprise who it reaches (Section 16).
    --}}
    <div class="mt-4 flex flex-wrap items-start gap-3 rounded-xl border border-gold/30 bg-gold/[0.06] px-4 py-3">
        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gold/15 text-gold-ink">
            <x-ui.icon name="users" class="h-4 w-4" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-ink">
                @if ($taking > 0 && $finished > 0)
                    {{ $taking }} {{ Str::plural('student', $taking) }} {{ $taking === 1 ? 'is' : 'are' }} taking
                    this course, and {{ $finished }} {{ $finished === 1 ? 'has' : 'have' }} finished it.
                @elseif ($taking > 0)
                    {{ $taking }} {{ Str::plural('student', $taking) }}
                    {{ $taking === 1 ? 'is' : 'are' }} taking this course right now.
                @else
                    {{ $finished }} {{ Str::plural('student', $finished) }}
                    {{ $finished === 1 ? 'has' : 'have' }} already finished this course.
                @endif
            </p>
            <p class="mt-0.5 text-sm text-ink/60">
                Changes that move a deadline, change what is graded, or change what they can reach
                are announced to them. Wording and media edits are not. Anything they have already
                worked on is hidden rather than deleted, so their record is kept.
            </p>
        </div>
    </div>
@endif
