@php
    /** @var \App\Models\Submission $submission */
    /** @var \App\Models\Assignment $assignment */
    /** @var \App\Models\Course $course */

    $fmtPts = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $isOwner = auth()->id() === $submission->user_id;
    $grade = $submission->grade;
@endphp

<x-app-layout :title="$assignment->title.' — version '.$submission->version">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <a href="{{ $isOwner ? route('assignments.show', [$course, $assignment]) : route('grading.assignments.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-ink focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> {{ $isOwner ? 'Back to assignment' : 'Back to grading' }}
            </a>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h2 class="font-display text-2xl font-semibold text-ink">{{ $assignment->title }}</h2>
                <x-ui.badge :variant="$submission->status->badge()">{{ $submission->status->label() }}</x-ui.badge>
                @if ($submission->is_late)
                    <x-ui.badge variant="crimson">Late</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-ink/65">
                Version {{ $submission->version }} · submitted {{ $submission->submitted_at->isoFormat('D MMM YYYY, HH:mm') }}
                @unless ($isOwner) · {{ $submission->user->name }} @endunless
            </p>
        </div>

        @if ($submission->isReturned())
            <div class="rounded-2xl border border-gold/40 bg-gold/10 p-4">
                <p class="text-sm font-semibold text-ink">Returned for resubmission</p>
                <p class="mt-1 whitespace-pre-line text-sm text-ink/80">{{ $submission->return_note }}</p>
                <p class="mt-1.5 text-xs text-ink/65">
                    {{ $submission->returned_at?->isoFormat('D MMM, HH:mm') }}{{ $submission->returnedBy ? ' · '.$submission->returnedBy->name : '' }}
                </p>
            </div>
        @endif

        @if (trim(strip_tags((string) $submission->body)) !== '')
            <x-ui.card>
                <h3 class="text-sm font-semibold text-ink/70">Typed answer</h3>
                <x-ui.prose class="mt-2 text-sm" :html="$submission->body" />
            </x-ui.card>
        @endif

        @if ($files->isNotEmpty())
            <x-ui.card>
                <h3 class="text-sm font-semibold text-ink/70">Files</h3>
                <ul class="mt-2 space-y-3">
                    @foreach ($files as $file)
                        @php $media = $file['media']; @endphp
                        <li class="overflow-hidden rounded-xl border border-line">
                            <div class="flex items-center gap-2.5 bg-surface/40 px-3 py-2.5">
                                <x-ui.icon name="document" class="h-4 w-4 shrink-0 text-ink/65" />
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink">{{ $media->original_name }}</span>
                                <span class="shrink-0 text-xs text-ink/65">{{ number_format($media->size_bytes / 1024) }} KB</span>
                                <a href="{{ route('media.download', $media) }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">
                                    <x-ui.icon name="download" class="h-4 w-4" /> Download
                                </a>
                            </div>
                            @if ($file['previewUrl'])
                                @if (str_starts_with((string) $media->mime, 'image/'))
                                    <img src="{{ $file['previewUrl'] }}" alt="Preview of {{ $media->original_name }}" class="max-h-96 w-full bg-ink/5 object-contain">
                                @else
                                    <iframe src="{{ $file['previewUrl'] }}" title="Preview of {{ $media->original_name }}" class="h-96 w-full bg-ink/5"></iframe>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        @if ($grade)
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-display text-lg font-semibold text-ink">Grade for this version</h3>
                    <p class="font-display text-xl font-semibold text-crimson">
                        {{ $fmtPts($grade->points_total) }}<span class="text-sm text-ink/65"> / {{ $fmtPts($assignment->max_points) }} pts</span>
                    </p>
                </div>
                <p class="mt-1 text-xs text-ink/65">Graded {{ $grade->graded_at->isoFormat('D MMM YYYY, HH:mm') }} by {{ $grade->grader->name }}.</p>

                @if ($grade->criterion_scores)
                    <ul class="mt-3 divide-y divide-line overflow-hidden rounded-xl border border-line">
                        @foreach ($grade->criterion_scores as $row)
                            <li class="flex items-center justify-between gap-3 bg-surface/40 px-4 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $row['criterion_title'] }}</p>
                                    <p class="text-xs text-ink/65">{{ $row['level_label'] }}</p>
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-ink">
                                    {{ $fmtPts($row['points']) }}<span class="font-normal text-ink/65"> / {{ $fmtPts($row['max_points'] ?? $row['points']) }}</span>
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($grade->feedback)
                    <x-ui.prose class="mt-3 rounded-xl bg-surface/60 p-4 text-sm" :html="$grade->feedback" />
                @endif
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
