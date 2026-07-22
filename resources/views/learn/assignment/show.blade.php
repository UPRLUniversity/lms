@php
    use App\Enums\MediaPurpose;

    /** @var \App\Models\Assignment $assignment */
    /** @var \App\Models\Course $course */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Submission> $versions */
    /** @var \App\Models\Submission|null $latest */
    /** @var \App\Models\Grade|null $grade */

    $fmtPts = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $acceptsNow = $assignment->acceptsSubmissionsNow();
    $total = $versions->count();
@endphp

<x-learn-layout :title="$assignment->title">
    <div class="mx-auto min-h-screen max-w-2xl px-4 py-10 sm:py-16"
         x-data="assignmentSubmit({
            draftKey: 'assignment-draft-{{ $assignment->id }}-{{ auth()->id() }}',
            maxFiles: 5,
            maxKb: {{ MediaPurpose::Submissions->maxKb() }},
         })">

        <a href="{{ route('learn.resume', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-crimson focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to course
        </a>

        {{-- The brief --}}
        <div class="mt-6 rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                        <x-ui.icon name="document-text" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-ink/50">Assignment · {{ $assignment->type->label() }}</p>
                        <h1 class="font-display text-2xl font-semibold text-ink">{{ $assignment->title }}</h1>
                    </div>
                </div>
                @if ($latest)
                    <x-ui.badge :variant="$latest->status->badge()">{{ $latest->status->label() }}</x-ui.badge>
                @endif
            </div>

            {{-- Due date / countdown --}}
            @if ($assignment->due_at)
                @php $pastDue = $assignment->isPastDue(); @endphp
                <div class="mt-5 flex flex-wrap items-center gap-2 rounded-xl p-3 text-sm
                            {{ $pastDue ? 'border border-crimson/30 bg-crimson/5' : 'border border-line bg-surface' }}">
                    <x-ui.icon name="clock" class="h-4 w-4 {{ $pastDue ? 'text-crimson' : 'text-ink/50' }}" />
                    <span class="text-ink/80">
                        Due {{ $assignment->due_at->isoFormat('ddd D MMM YYYY, HH:mm') }}
                        @unless ($pastDue)
                            · <span class="font-medium text-ink">{{ $assignment->due_at->diffForHumans(['parts' => 2]) }}</span>
                        @endunless
                    </span>
                    @if ($pastDue)
                        <span class="font-medium text-crimson">
                            {{ $assignment->allow_late ? 'Past due — late submissions are accepted and badged.' : 'Closed — the deadline has passed.' }}
                        </span>
                    @endif
                </div>
            @endif

            <dl class="mt-5 grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-surface p-3">
                    <dt class="text-xs text-ink/50">Graded out of</dt>
                    <dd class="mt-0.5 font-display text-lg font-semibold text-ink">{{ $assignment->max_points ? $fmtPts($assignment->max_points).' pts' : '—' }}</dd>
                </div>
                <div class="rounded-xl bg-surface p-3">
                    <dt class="text-xs text-ink/50">Your versions</dt>
                    <dd class="mt-0.5 font-display text-lg font-semibold text-ink">{{ $total ?: 'None yet' }}</dd>
                </div>
            </dl>

            @if ($assignment->instructions)
                <x-ui.prose class="mt-6 text-sm" :html="$assignment->instructions" />
            @endif

            @if ($resources->isNotEmpty())
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-ink/70">Resources</h2>
                    <ul class="mt-2 space-y-2">
                        @foreach ($resources as $resource)
                            <li>
                                <a href="{{ route('media.download', $resource) }}"
                                   class="flex items-center gap-2.5 rounded-xl border border-line bg-surface/40 p-3 text-sm transition hover:border-crimson/30 focus-ring">
                                    <x-ui.icon name="download" class="h-4 w-4 shrink-0 text-crimson" />
                                    <span class="min-w-0 flex-1 truncate font-medium text-ink">{{ $resource->original_name }}</span>
                                    <span class="shrink-0 text-xs text-ink/45">{{ number_format($resource->size_bytes / 1024) }} KB</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Returned-for-resubmission note --}}
        @if ($latest?->isReturned())
            <div class="mt-6 rounded-2xl border border-gold/40 bg-gold/10 p-5">
                <div class="flex items-center gap-2">
                    <x-ui.icon name="arrow-path" class="h-5 w-5 text-gold-ink" />
                    <h2 class="font-display text-base font-semibold text-ink">Your work was returned for another version</h2>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-ink/80">{{ $latest->return_note }}</p>
                <p class="mt-2 text-xs text-ink/50">
                    Returned {{ $latest->returned_at?->isoFormat('D MMM, HH:mm') }}{{ $latest->returnedBy ? ' by '.$latest->returnedBy->name : '' }}.
                    Submit a new version below when you're ready.
                </p>
            </div>
        @endif

        {{-- Graded result --}}
        @if ($grade)
            <div class="mt-6 rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold text-ink">Your grade</h2>
                    <p class="font-display text-2xl font-semibold text-crimson">
                        {{ $fmtPts($grade->points_total) }}<span class="text-base text-ink/40"> / {{ $fmtPts($assignment->max_points) }} pts</span>
                    </p>
                </div>
                <p class="mt-1 text-xs text-ink/50">Graded {{ $grade->graded_at->isoFormat('D MMM YYYY, HH:mm') }} on version {{ $latest->version }}.</p>

                @if ($grade->criterion_scores)
                    <ul class="mt-4 divide-y divide-line overflow-hidden rounded-xl border border-line">
                        @foreach ($grade->criterion_scores as $row)
                            <li class="flex items-center justify-between gap-3 bg-surface/40 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $row['criterion_title'] }}</p>
                                    <p class="text-xs text-ink/55">{{ $row['level_label'] }}</p>
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-ink">
                                    {{ $fmtPts($row['points']) }}<span class="font-normal text-ink/40"> / {{ $fmtPts($row['max_points'] ?? $row['points']) }}</span>
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($grade->feedback)
                    <div class="mt-4">
                        <h3 class="text-sm font-semibold text-ink/70">Feedback</h3>
                        <x-ui.prose class="mt-1.5 rounded-xl bg-surface/60 p-4 text-sm" :html="$grade->feedback" />
                    </div>
                @endif
            </div>
        @endif

        {{-- Submit form --}}
        @if ($acceptsNow)
            <div class="mt-6 rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
                <h2 class="font-display text-lg font-semibold text-ink">
                    {{ $total === 0 ? 'Hand in your work' : 'Submit a new version' }}
                </h2>
                @if ($total > 0)
                    <p class="mt-1 text-sm text-ink/60">This will become version {{ $total + 1 }}. Earlier versions stay in your history unchanged.</p>
                @endif
                @if ($assignment->isPastDue() && $assignment->allow_late)
                    <p class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-crimson/5 px-2.5 py-1.5 text-xs font-medium text-crimson">
                        <x-ui.icon name="clock" class="h-3.5 w-3.5" /> The deadline has passed — this submission will be marked LATE.
                    </p>
                @endif

                <form method="POST" action="{{ route('submissions.store', [$course, $assignment]) }}"
                      enctype="multipart/form-data" class="mt-4 space-y-4" @submit.prevent="submit()">
                    @csrf

                    @if ($assignment->type->acceptsText())
                        <div>
                            <x-ui.rich-editor
                                name="body"
                                id="submission-body"
                                label="Your answer"
                                profile="basic"
                                :value="old('body')"
                                :height="240"
                                placeholder="Write your answer here…" />
                            <p class="mt-1 text-xs text-ink/45" x-show="draftSavedAt" x-cloak>
                                Draft saved on this device at <span x-text="draftSavedAt"></span>.
                            </p>
                        </div>
                    @endif

                    @if ($assignment->type->acceptsFiles())
                        <div>
                            <label for="submission-files" class="block text-sm font-medium text-ink">
                                Files <span class="font-normal text-ink/40">(up to 5 · PDF, Word, ZIP, text or images · {{ (int) round(MediaPurpose::Submissions->maxKb() / 1024) }}MB each)</span>
                            </label>
                            <input id="submission-files" type="file" multiple @change="pick($event)"
                                   accept="{{ implode(',', MediaPurpose::Submissions->allowedMimes()) }}"
                                   class="mt-1.5 block w-full text-sm text-ink/70 file:mr-3 file:rounded-lg file:border-0 file:bg-crimson/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-crimson hover:file:bg-crimson/15">

                            <ul class="mt-2 space-y-1.5" x-show="files.length" x-cloak>
                                <template x-for="(file, i) in files" :key="i">
                                    <li class="flex items-center gap-2 rounded-lg border border-line bg-surface/40 px-3 py-2 text-sm">
                                        <x-ui.icon name="document" class="h-4 w-4 shrink-0 text-ink/40" />
                                        <span class="min-w-0 flex-1 truncate text-ink" x-text="file.name"></span>
                                        <span class="shrink-0 text-xs text-ink/45" x-text="fmtSize(file.size)"></span>
                                        <button type="button" @click="removeFile(i)" class="rounded p-0.5 text-ink/35 hover:text-crimson focus-ring" aria-label="Remove file">
                                            <x-ui.icon name="x" class="h-3.5 w-3.5" />
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    @endif

                    {{-- Server / client errors --}}
                    <template x-if="errors.length">
                        <div class="rounded-xl border border-crimson/30 bg-crimson/5 p-3">
                            <template x-for="error in errors" :key="error">
                                <p class="text-sm text-crimson" x-text="error"></p>
                            </template>
                        </div>
                    </template>
                    @if ($errors->any())
                        <div class="rounded-xl border border-crimson/30 bg-crimson/5 p-3">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-crimson">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    {{-- Upload progress --}}
                    <div x-show="uploading" x-cloak>
                        <div class="flex items-center justify-between text-xs font-medium text-ink/60">
                            <span>Uploading…</span>
                            <span x-text="progress + '%'"></span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-ink/5" role="progressbar"
                             :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="Upload progress">
                            <div class="h-full rounded-full bg-crimson transition-[width] duration-200" :style="`width: ${progress}%`"></div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-ui.button type="submit" x-bind:disabled="uploading">
                            <x-ui.icon name="check" class="h-4 w-4" />
                            {{ $total === 0 ? 'Submit for grading' : 'Submit version '.($total + 1) }}
                        </x-ui.button>
                    </div>
                </form>
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-line bg-card p-6 text-center shadow-sm">
                <x-ui.icon name="lock" class="mx-auto h-8 w-8 text-ink/30" />
                <h2 class="mt-2 font-display text-lg font-semibold text-ink">Submissions have closed</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-ink/60">
                    The deadline was {{ $assignment->due_at->isoFormat('D MMM YYYY, HH:mm') }} and this assignment doesn't accept late work.
                    If you believe this is an error, please contact your instructor.
                </p>
            </div>
        @endif

        {{-- Version history --}}
        @if ($versions->isNotEmpty())
            <div class="mt-6">
                <h2 class="font-display text-sm font-semibold text-ink/70">Version history</h2>
                <ul class="mt-2 divide-y divide-line overflow-hidden rounded-xl border border-line bg-card">
                    @foreach ($versions as $version)
                        <li class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="text-sm font-medium text-ink">
                                    Version {{ $version->version }} of {{ $total }},
                                    submitted {{ $version->submitted_at->isoFormat('D MMM, HH:mm') }}
                                </span>
                                @if ($version->is_late)
                                    <x-ui.badge variant="crimson">Late</x-ui.badge>
                                @endif
                                <x-ui.badge :variant="$version->status->badge()">{{ $version->status->label() }}</x-ui.badge>
                            </div>
                            <a href="{{ route('submissions.show', $version) }}" class="shrink-0 text-sm font-medium text-crimson hover:text-crimson-dark focus-ring rounded">View</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-learn-layout>
