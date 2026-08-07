@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Course $course */
    /** @var ?\App\Models\GradeScale $scale */
    /** @var \Illuminate\Support\Collection $items */
    /** @var ?\App\Support\Grades\GradebookSummary $summary */
    /** @var ?\App\Models\CourseGradeRecord $record */

    $fmtNum = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-app-layout :title="'Grades · '.$course->title">
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <a href="{{ route('learn.resume', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/50 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
            </a>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-ink">Grades</h2>
                    <p class="mt-1 text-ink/70">{{ $course->title }} · {{ $course->code }}</p>
                </div>
            </div>
        </div>

        @if (! $scale || $items->isEmpty())
            <x-ui.empty-state
                icon="clipboard-check"
                title="No gradable coursework yet"
                description="This course has no required, graded assessments or assignments — its gradebook stays empty." />
        @else
            {{-- Summary card --}}
            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm text-ink/60">Overall grade</p>
                            @if ($record)
                                <x-ui.badge variant="success">Final</x-ui.badge>
                            @elseif ($summary->provisional)
                                <x-ui.badge variant="gold">Provisional</x-ui.badge>
                            @endif

                            @php
                                // The recorded verdict comes from the scale the student was
                                // measured under; before completion it's the live one.
                                $isPass = $record ? $record->isPass() : $summary->isPass();
                            @endphp
                            @if ($isPass !== null)
                                <x-ui.badge :variant="$isPass ? 'success' : 'crimson'">{{ $isPass ? 'Pass' : 'Fail' }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 font-display text-2xl font-semibold text-ink">
                            @if ($record)
                                {{ $record->formatResult() }}
                            @elseif ($summary->percent !== null)
                                {{ $scale->formatResult($summary->percent, $summary->band) }}
                            @else
                                <span class="text-ink/40 text-base font-normal">Nothing graded yet</span>
                            @endif
                        </p>
                        @if ($record)
                            <p class="mt-1 text-xs text-ink/50">Recorded {{ $record->computed_at->isoFormat('D MMM YYYY') }} — won't change even if the scale is edited later.</p>
                        @elseif ($summary->provisional)
                            <p class="mt-1 text-xs text-ink/50">Some coursework is still awaiting grading — this figure will update as it's graded.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Item table --}}
            <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                                <th scope="col" class="px-5 py-3.5">Coursework</th>
                                <th scope="col" class="px-5 py-3.5">Score</th>
                                <th scope="col" class="px-5 py-3.5">%</th>
                                <th scope="col" class="px-5 py-3.5">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($items as $item)
                                @php $band = ! $item->pending && $item->percent !== null ? $scale->bandFor($item->percent) : null; @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <x-ui.icon :name="$item->isAssignment() ? 'document-text' : 'clipboard'" class="h-4 w-4 shrink-0 text-ink/40" />
                                            <span class="font-medium text-ink">{{ $item->title() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-ink/70">
                                        @if ($item->pending)
                                            <span class="text-ink/30">—</span>
                                        @else
                                            {{ $fmtNum($item->pointsEarned) }} / {{ $fmtNum($item->pointsPossible) }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-ink/70">
                                        {{ $item->pending ? '—' : round($item->percent).'%' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($item->pending)
                                            <x-ui.badge variant="neutral">Pending</x-ui.badge>
                                        @elseif ($band)
                                            <x-ui.badge :variant="$band->color">{{ $band->label }}</x-ui.badge>
                                        @else
                                            <span class="text-ink/30">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="text-xs text-ink/40">
                Displayed as {{ $scale->name }} · limit {{ $fmtNum($scale->scale_limit) }}@if ($scale->passMark() !== null) · pass mark {{ $scale->passMark() }}%@endif.
                Per-item grades are informational and never averaged directly — the overall grade is points-weighted across all coursework.
            </p>
        @endif
    </div>
</x-app-layout>
