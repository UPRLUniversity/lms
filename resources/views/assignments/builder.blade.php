@php
    use App\Enums\AssignmentType;
    use Illuminate\Support\Str;

    /** @var \App\Models\Assignment $assignment */
    /** @var \App\Models\Course $course */

    $fmtPts = fn ($n) => $n === null ? null : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-app-layout :title="$assignment->title.' — assignment'">
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <a href="{{ route('courses.edit', $course) }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> {{ $course->title }}
                </a>
                <div class="mt-1 flex items-center gap-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $assignment->title }}</h2>
                    <x-ui.badge :variant="$assignment->status->badge()">{{ $assignment->status->label() }}</x-ui.badge>
                </div>
                <p class="mt-1 text-sm text-ink/60">
                    {{ $assignment->module ? 'After module “'.$assignment->module->title.'”' : 'Standalone (end of course)' }}
                    · {{ $submissionCount }} {{ Str::plural('submission', $submissionCount) }}
                </p>
            </div>

            @if ($canManage)
                <div class="flex items-center gap-2">
                    @if ($assignment->isPublished())
                        <form method="POST" action="{{ route('assignments.unpublish', [$course, $assignment]) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">Unpublish</x-ui.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('assignments.publish', [$course, $assignment]) }}">
                            @csrf
                            <x-ui.button type="submit" :disabled="! empty($publishErrors)">
                                <x-ui.icon name="check" class="h-4 w-4" /> Publish
                            </x-ui.button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- Publish gate feedback --}}
        @if (! empty($publishErrors) && ! $assignment->isPublished())
            <div class="rounded-xl border border-gold/40 bg-gold/10 p-4">
                <p class="text-sm font-medium text-ink">Before this can be published:</p>
                <ul class="mt-1.5 list-disc space-y-1 pl-5 text-sm text-ink/75">
                    @foreach ($publishErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr,20rem]">
            {{-- Settings form --}}
            <form method="POST" action="{{ route('assignments.update', [$course, $assignment]) }}" class="space-y-5">
                @csrf @method('PUT')

                <x-ui.card class="space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-ink">Title</label>
                        <input id="title" name="title" required maxlength="255" value="{{ old('title', $assignment->title) }}"
                               @disabled(! $canManage)
                               class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                        @error('title')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                    </div>

                    @if ($canManage)
                        <x-ui.rich-editor
                            name="instructions"
                            label="Instructions"
                            :value="old('instructions', $assignment->instructions)"
                            hint="Tell students exactly what to produce, how it will be graded and how to hand it in."
                            :height="280" />
                        @error('instructions')<p class="text-sm text-crimson">{{ $message }}</p>@enderror
                    @else
                        <div>
                            <p class="block text-sm font-medium text-ink">Instructions</p>
                            <x-ui.prose class="mt-1.5 rounded-xl border border-line bg-surface/40 p-4 text-sm" :html="$assignment->instructions ?: '<p>—</p>'" />
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="type" class="block text-sm font-medium text-ink">Students hand in</label>
                            <select id="type" name="type" @disabled(! $canManage)
                                    class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                @foreach (AssignmentType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('type', $assignment->type->value) === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="module_id" class="block text-sm font-medium text-ink">Placement in curriculum</label>
                            <select id="module_id" name="module_id" @disabled(! $canManage)
                                    class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <option value="">Standalone (end of course)</option>
                                @foreach ($modules as $module)
                                    <option value="{{ $module->id }}" @selected((int) old('module_id', $assignment->module_id) === $module->id)>After “{{ $module->title }}”</option>
                                @endforeach
                            </select>
                            @error('module_id')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card class="space-y-4">
                    <h3 class="font-display text-lg font-semibold text-ink">Deadline & grading</h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="due_at" class="block text-sm font-medium text-ink">Due <span class="font-normal text-ink/40">(optional)</span></label>
                            <input id="due_at" name="due_at" type="datetime-local" @disabled(! $canManage)
                                   value="{{ old('due_at', $assignment->due_at?->format('Y-m-d\TH:i')) }}"
                                   class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            @error('due_at')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="max_points" class="block text-sm font-medium text-ink">Graded out of</label>
                            <div class="mt-1.5 flex items-center gap-2">
                                <input id="max_points" name="max_points" type="number" min="0" step="0.5" @disabled(! $canManage)
                                       value="{{ old('max_points', $fmtPts($assignment->max_points)) }}"
                                       class="block w-32 rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                                <span class="text-sm text-ink/50">points</span>
                            </div>
                            @error('max_points')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-x-8 gap-y-3">
                        <label class="inline-flex items-center gap-2.5 text-sm text-ink">
                            <input type="hidden" name="allow_late" value="0">
                            <input type="checkbox" name="allow_late" value="1" @checked(old('allow_late', $assignment->allow_late)) @disabled(! $canManage)
                                   class="rounded border-line text-crimson focus:ring-crimson">
                            Accept late submissions <span class="text-ink/45">(badged LATE)</span>
                        </label>
                        {{-- Two independent questions: must they hand it in, and does the
                             score count? A formative draft can be compulsory and still
                             stay out of the transcript. --}}
                        <label class="inline-flex items-start gap-2.5 text-sm text-ink">
                            <input type="hidden" name="is_required" value="0">
                            <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $assignment->is_required)) @disabled(! $canManage)
                                   class="mt-0.5 rounded border-line text-crimson focus:ring-crimson">
                            <span>
                                Required
                                <span class="block text-ink/55">Students must complete this to finish the course.</span>
                            </span>
                        </label>
                        <label class="inline-flex items-start gap-2.5 text-sm text-ink">
                            <input type="hidden" name="counts_toward_grade" value="0">
                            <input type="checkbox" name="counts_toward_grade" value="1"
                                   @checked(old('counts_toward_grade', $assignment->counts_toward_grade)) @disabled(! $canManage)
                                   class="mt-0.5 rounded border-line text-crimson focus:ring-crimson">
                            <span>
                                Include this score in the course grade
                                <span class="block text-ink/55">Untick for practice work — it still gates progress, but stays out of the gradebook.</span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <label for="rubric_id" class="block text-sm font-medium text-ink">Rubric <span class="font-normal text-ink/40">(optional)</span></label>
                        <select id="rubric_id" name="rubric_id" @disabled(! $canManage)
                                class="mt-1.5 block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                            <option value="">No rubric — grade with a single score</option>
                            @foreach ($rubrics as $rubric)
                                <option value="{{ $rubric->id }}" @selected((int) old('rubric_id', $assignment->rubric_id) === $rubric->id)>
                                    {{ $rubric->name }} ({{ $rubric->criteria_count }} {{ Str::plural('criterion', $rubric->criteria_count) }})
                                </option>
                            @endforeach
                        </select>
                        @error('rubric_id')<p class="mt-1 text-sm text-crimson">{{ $message }}</p>@enderror
                        <p class="mt-1.5 text-xs text-ink/55">
                            Grading with a rubric fills the score from your level choices.
                            <a href="{{ route('rubrics.index') }}" class="font-medium text-crimson hover:text-crimson-dark focus-ring rounded">Manage rubrics</a>
                        </p>
                    </div>

                    @if ($assignment->rubric)
                        {{-- Read-only preview of the attached rubric --}}
                        <div class="rounded-xl border border-line bg-surface/40 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink/45">{{ $assignment->rubric->name }} · {{ $fmtPts($assignment->rubric->totalPoints()) }} pts max</p>
                            <ul class="mt-2 space-y-1 text-sm text-ink/75">
                                @foreach ($assignment->rubric->criteria as $criterion)
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="truncate">{{ $criterion->title }}</span>
                                        <span class="shrink-0 text-xs text-ink/45">{{ collect($criterion->levels)->map(fn ($l) => $fmtPts($l['points']))->implode(' / ') }} pts</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-ui.card>

                @if ($canManage)
                    <div class="flex justify-end gap-2">
                        <x-ui.button type="submit">Save assignment</x-ui.button>
                    </div>
                @endif
            </form>

            {{-- Side panel: resources + danger zone --}}
            <div class="space-y-5">
                <x-ui.card>
                    <h3 class="font-display text-base font-semibold text-ink">Resources</h3>
                    <p class="mt-1 text-xs text-ink/55">Briefs, templates or datasets students need. Enrolled students can download them from the assignment page.</p>

                    @if ($resources->isEmpty())
                        <p class="mt-3 text-sm text-ink/40">No resources attached.</p>
                    @else
                        <ul class="mt-3 space-y-2">
                            @foreach ($resources as $resource)
                                <li class="flex items-center gap-2 rounded-lg border border-line bg-surface/40 p-2.5">
                                    <x-ui.icon name="document" class="h-4 w-4 shrink-0 text-ink/40" />
                                    <a href="{{ route('media.download', $resource) }}" class="min-w-0 flex-1 truncate text-sm font-medium text-ink hover:text-crimson focus-ring rounded">
                                        {{ $resource->original_name }}
                                    </a>
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('assignments.resources.destroy', [$course, $assignment, $resource]) }}"
                                              onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Remove this resource?', confirmText: 'Remove' }).then(ok => ok && this.submit());">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded p-1 text-ink/35 hover:text-crimson focus-ring" aria-label="Remove resource">
                                                <x-ui.icon name="x" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($canManage)
                        <form method="POST" action="{{ route('assignments.resources.store', [$course, $assignment]) }}"
                              enctype="multipart/form-data" class="mt-3 space-y-2">
                            @csrf
                            <label for="resource-file" class="sr-only">Attach a resource file</label>
                            <input id="resource-file" type="file" name="file" required
                                   class="block w-full text-sm text-ink/70 file:mr-3 file:rounded-lg file:border-0 file:bg-crimson/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-crimson hover:file:bg-crimson/15">
                            @error('file')<p class="text-sm text-crimson">{{ $message }}</p>@enderror
                            <x-ui.button type="submit" variant="secondary" size="sm" class="w-full justify-center">
                                <x-ui.icon name="plus" class="h-4 w-4" /> Attach resource
                            </x-ui.button>
                        </form>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <h3 class="font-display text-base font-semibold text-ink">Submissions</h3>
                    <p class="mt-1 text-sm text-ink/60">{{ $submissionCount }} {{ Str::plural('version', $submissionCount) }} handed in.</p>
                    <x-ui.button variant="secondary" size="sm" class="mt-3 w-full justify-center"
                        :href="route('grading.assignments.index', ['assignment' => $assignment->id, 'course' => $course->slug])">
                        <x-ui.icon name="clipboard-check" class="h-4 w-4" /> Open grading queue
                    </x-ui.button>
                </x-ui.card>

                @if ($canManage)
                    <x-ui.card>
                        <h3 class="font-display text-base font-semibold text-ink">Danger zone</h3>
                        <p class="mt-1 text-xs text-ink/55">Deleting removes the assignment and every submission version with it.</p>
                        <form method="POST" action="{{ route('assignments.destroy', [$course, $assignment]) }}" class="mt-3"
                              onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Delete this assignment?', text: 'All student submissions and grades on it will be removed too. This cannot be undone.', confirmText: 'Delete assignment' }).then(ok => ok && this.submit());">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm" class="w-full justify-center text-crimson">
                                <x-ui.icon name="trash" class="h-4 w-4" /> Delete assignment
                            </x-ui.button>
                        </form>
                    </x-ui.card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
