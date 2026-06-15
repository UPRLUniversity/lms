@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\Lesson $lesson */
    /** @var \App\Support\Learning\CourseProgress $snapshot */

    $doneIds = $snapshot->sequence
        ->filter(fn ($l) => $snapshot->isComplete($l))
        ->mapWithKeys(fn ($l) => [$l->id => true])
        ->all();

    $config = [
        'percent' => $snapshot->percent(),
        'done' => (object) $doneIds,
        'currentId' => $lesson->id,
        'canTrack' => $canTrack,
        'isUploadedVideo' => $lesson->isUploadedVideo(),
        'resumePosition' => $snapshot->lastPosition($lesson),
        'routes' => [
            'complete' => route('learn.complete', [$course, $lesson]),
            'incomplete' => route('learn.incomplete', [$course, $lesson]),
            'position' => route('learn.position', [$course, $lesson]),
            'next' => $next ? route('learn.show', [$course, $next]) : null,
            'previous' => $previous ? route('learn.show', [$course, $previous]) : null,
        ],
    ];
@endphp

<x-learn-layout :title="$lesson->title">
    <div x-data="learnPlayer(@js($config))" x-init="init()"
         @keydown.window="onKey($event)"
         class="min-h-screen">

        @include('learn.partials._sidebar')

        {{-- Mobile drawer backdrop --}}
        <div x-show="drawer" x-transition.opacity @click="drawer = false"
             class="fixed inset-0 z-30 bg-ink/50 lg:hidden" x-cloak></div>

        {{-- Main column — shifts for the fixed sidebar unless in focus mode --}}
        <div class="flex min-h-screen flex-col transition-[padding] duration-200"
             :class="collapsed ? 'lg:pl-0' : 'lg:pl-80'">

            {{-- Top bar --}}
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-card/90 px-4 backdrop-blur sm:px-6">
                {{-- Mobile: open curriculum --}}
                <button type="button" @click="drawer = true"
                        class="rounded-lg p-2 text-ink/60 hover:bg-ink/5 hover:text-ink focus-ring lg:hidden"
                        aria-label="Open curriculum">
                    <x-ui.icon name="list" class="h-5 w-5" />
                </button>
                {{-- Desktop: focus mode (show/hide curriculum) --}}
                <button type="button" @click="collapsed = ! collapsed"
                        class="hidden rounded-lg p-2 text-ink/60 hover:bg-ink/5 hover:text-ink focus-ring lg:inline-flex"
                        :aria-pressed="collapsed.toString()"
                        :title="collapsed ? 'Show curriculum' : 'Hide curriculum (focus mode)'">
                    <x-ui.icon name="panel-left" class="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-ink">{{ $course->title }}</p>
                </div>

                {{-- Compact progress in the bar --}}
                <div class="hidden items-center gap-2 sm:flex">
                    <div class="h-1.5 w-28 overflow-hidden rounded-full bg-ink/5">
                        <div class="h-full rounded-full bg-crimson transition-[width] duration-500" :style="`width: ${percent}%`"></div>
                    </div>
                    <span class="text-xs font-medium text-ink/60" x-text="percent + '%'"></span>
                </div>

                <x-ui.button size="sm" variant="ghost" :href="route('learning.index')" class="hidden sm:inline-flex">
                    Exit
                </x-ui.button>
            </header>

            {{-- Preview banner (staff/auditor) --}}
            @if ($isPreview)
                <div class="border-b border-gold/30 bg-gold/10 px-4 py-2.5 text-center text-sm text-ink/70 sm:px-6">
                    <x-ui.icon name="eye" class="mr-1 inline h-4 w-4" /> You're previewing this course — progress isn't tracked.
                </div>
            @endif

            {{-- Lesson content --}}
            <main id="lesson-content" class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                @include('learn.partials._lesson')
            </main>

            {{-- Flow controls --}}
            <footer class="sticky bottom-0 z-20 border-t border-line bg-card/95 px-4 py-3 backdrop-blur sm:px-6">
                <div class="mx-auto flex max-w-4xl items-center justify-between gap-3">
                    {{-- Previous --}}
                    @if ($previous)
                        <x-ui.button variant="secondary" :href="route('learn.show', [$course, $previous])">
                            <x-ui.icon name="chevron-left" class="h-5 w-5" /> <span class="hidden sm:inline">Previous</span>
                        </x-ui.button>
                    @else
                        <span class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm text-ink/30">
                            <x-ui.icon name="chevron-left" class="h-5 w-5" /> <span class="hidden sm:inline">Previous</span>
                        </span>
                    @endif

                    @if ($canTrack)
                        <div class="flex items-center gap-2">
                            {{-- Mark as incomplete (only when done) --}}
                            <button type="button" x-show="isDone(currentId)" x-cloak
                                    @click="markIncomplete()"
                                    class="rounded-xl px-3 py-2 text-sm font-medium text-ink/50 hover:text-crimson focus-ring">
                                Mark as incomplete
                            </button>

                            {{-- Not done → Complete & Continue --}}
                            <form x-show="! isDone(currentId)" method="POST"
                                  action="{{ route('learn.complete', [$course, $lesson]) }}"
                                  @submit.prevent="complete()">
                                @csrf
                                <x-ui.button type="submit" size="lg" ::disabled="completing" x-bind:aria-busy="completing">
                                    <span x-show="! completing">Complete &amp; Continue</span>
                                    <span x-show="completing" x-cloak>Saving…</span>
                                    <x-ui.icon name="arrow-right" class="h-5 w-5" />
                                </x-ui.button>
                            </form>

                            {{-- Done → advance --}}
                            <div x-show="isDone(currentId)" x-cloak>
                                @if ($next)
                                    <x-ui.button size="lg" :href="route('learn.show', [$course, $next])">
                                        Next lesson <x-ui.icon name="arrow-right" class="h-5 w-5" />
                                    </x-ui.button>
                                @else
                                    <x-ui.button size="lg" :href="route('learn.congratulations', $course)">
                                        Finish course <x-ui.icon name="sparkles" class="h-5 w-5" />
                                    </x-ui.button>
                                @endif
                            </div>
                        </div>
                    @elseif ($next)
                        <x-ui.button size="lg" :href="route('learn.show', [$course, $next])">
                            Next <x-ui.icon name="arrow-right" class="h-5 w-5" />
                        </x-ui.button>
                    @else
                        <span></span>
                    @endif
                </div>
            </footer>
        </div>

        {{-- Celebration micro-moment (module / course completion) --}}
        <div x-show="celebrating" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 backdrop-blur-sm"
             role="dialog" aria-live="assertive">
            <div class="relative mx-4 w-full max-w-sm overflow-hidden rounded-2xl border border-line bg-card p-8 text-center shadow-xl"
                 x-transition:enter="transition ease-out duration-300 delay-75"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <x-brand.sunburst class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 text-gold/15" />
                <span class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success/10 text-success">
                    <x-ui.icon name="check" class="h-8 w-8" stroke-width="2.5" />
                </span>
                <h3 class="relative mt-5 font-display text-2xl font-semibold text-ink" x-text="celebration.title"></h3>
                <p class="relative mt-2 text-ink/70" x-text="celebration.message"></p>
            </div>
        </div>
    </div>
</x-learn-layout>
