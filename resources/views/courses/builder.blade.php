@php
    use App\Enums\CourseStatus;

    $isDraft = $course->status === CourseStatus::Draft;
    $isReview = $course->status === CourseStatus::Review;
    $isPublished = $course->status === CourseStatus::Published;
    $isArchived = $course->status === CourseStatus::Archived;
    $ready = empty($publishBlockers);

    $builderConfig = [
        'base' => url('manage/courses/'.$course->slug),
        'csrf' => csrf_token(),
        'canManage' => $canManage,
    ];
@endphp

<x-app-layout :title="$course->title">
    <div class="mx-auto max-w-5xl"
         x-data="courseBuilder(@js($builderConfig))" x-init="init()">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('courses.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to courses
                </a>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="font-display text-2xl font-semibold text-ink">{{ $course->title }}</h2>
                    <x-ui.badge :variant="$course->status->badge()">{{ $course->status->label() }}</x-ui.badge>
                </div>
                <p class="mt-1 text-sm text-ink/60">{{ $course->code }} · {{ $course->department?->name ?? 'No department' }}</p>
            </div>

            {{-- Workflow actions --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($isPublished)
                    <x-ui.button variant="secondary" size="sm" :href="route('catalogue.show', $course)">
                        <x-ui.icon name="eye" class="h-4 w-4" /> View live
                    </x-ui.button>
                    @can('viewRoster', $course)
                        <x-ui.button variant="secondary" size="sm" :href="route('courses.progress', $course)">
                            <x-ui.icon name="chart" class="h-4 w-4" /> Progress
                        </x-ui.button>
                    @endcan
                @endif

                @if ($canManage && $isDraft)
                    <form method="POST" action="{{ route('courses.submit', $course) }}">
                        @csrf
                        <x-ui.button type="submit" :disabled="! $ready"
                                     title="{{ $ready ? 'Submit this course for admin review' : 'Resolve the publish checklist first' }}">
                            Submit for review
                        </x-ui.button>
                    </form>
                @endif

                @can('review', $course)
                    @if ($isReview)
                        <form method="POST" action="{{ route('courses.publish', $course) }}">
                            @csrf
                            <x-ui.button type="submit"><x-ui.icon name="check" class="h-4 w-4" /> Publish</x-ui.button>
                        </form>
                        <x-ui.button variant="danger" size="md" @click="$dispatch('open-modal', 'return-course')">
                            Return with note
                        </x-ui.button>
                    @endif
                    @if ($isPublished)
                        <form method="POST" action="{{ route('courses.archive', $course) }}" data-confirm>
                            @csrf
                            <x-ui.button type="submit" variant="secondary">Archive</x-ui.button>
                        </form>
                    @endif
                    @if ($isArchived)
                        <form method="POST" action="{{ route('courses.restore', $course) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">Restore to catalogue</x-ui.button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        {{-- Returned-with-note banner (shown in-app per the spec) --}}
        @if ($isDraft && $course->review_note)
            <div class="mt-5 flex gap-3 rounded-xl border border-crimson/30 bg-crimson/5 p-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-crimson/10 text-crimson">
                    <x-ui.icon name="inbox" class="h-5 w-5" />
                </span>
                <div>
                    <p class="font-medium text-ink">Changes requested by the reviewer</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink/80">{{ $course->review_note }}</p>
                </div>
            </div>
        @endif

        {{-- In-review notice --}}
        @if ($isReview)
            <div class="mt-5 flex gap-3 rounded-xl border border-gold/40 bg-gold/10 p-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gold/20 text-gold">
                    <x-ui.icon name="clock" class="h-5 w-5" />
                </span>
                <p class="text-sm text-ink/80">
                    This course is awaiting review.
                    @can('review', $course) Publish it, or return it to the instructor with a note. @else An admin will review it shortly. @endcan
                </p>
            </div>
        @endif

        {{-- Tabs --}}
        <div class="mt-6 border-b border-line">
            <nav class="-mb-px flex gap-6" aria-label="Builder sections">
                <button type="button" @click="tab = 'settings'"
                        :class="tab === 'settings' ? 'border-crimson text-crimson' : 'border-transparent text-ink/60 hover:text-ink'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium focus-ring rounded-t">Settings</button>
                <button type="button" @click="tab = 'curriculum'"
                        :class="tab === 'curriculum' ? 'border-crimson text-crimson' : 'border-transparent text-ink/60 hover:text-ink'"
                        class="border-b-2 px-1 pb-3 text-sm font-medium focus-ring rounded-t">
                    Curriculum
                </button>
            </nav>
        </div>

        {{-- SETTINGS TAB --}}
        <div x-show="tab === 'settings'" class="mt-6">
            @include('courses.partials._settings')
        </div>

        {{-- CURRICULUM TAB --}}
        <div x-show="tab === 'curriculum'" x-cloak class="mt-6">
            @include('courses.partials._curriculum_tab')
        </div>

        {{-- Lesson editor slide-over --}}
        @if ($canManage)
            @include('courses.partials._lesson_editor')
        @endif

        {{-- Return-with-note modal --}}
        @can('review', $course)
            <x-ui.modal name="return-course" title="Return to the instructor">
                <form method="POST" action="{{ route('courses.return', $course) }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-ink/70">Explain what needs to change. The instructor will see this note on the course.</p>
                    <div>
                        <label for="review_note" class="sr-only">Note</label>
                        <textarea id="review_note" name="review_note" rows="4" required
                                  class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                  placeholder="e.g. Please expand Module 2 and add a summary."></textarea>
                        <x-input-error :messages="$errors->get('review_note')" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="ghost" @click="$dispatch('close-modal', 'return-course')">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="danger">Return course</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endcan
    </div>
</x-app-layout>
