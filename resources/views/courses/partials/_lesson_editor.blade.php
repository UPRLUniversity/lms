@php
    use App\Enums\LessonType;

    $lessonMediaMaxKb = (int) config('media.purposes.lesson_media.max_kb', 25600);
@endphp

{{-- Slide-over lesson editor. Driven by the courseBuilder Alpine component. --}}
<div x-show="editorOpen" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Lesson editor"
     @keydown.escape.window="closeEditor()">
    {{-- Backdrop --}}
    <div x-show="editorOpen" x-transition.opacity class="absolute inset-0 bg-ink/50" @click="closeEditor()"></div>

    {{-- Panel --}}
    <div x-show="editorOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-card shadow-xl">

        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <h3 class="font-display text-lg font-semibold text-ink" x-text="lesson.id ? 'Edit lesson' : 'Add lesson'">Add lesson</h3>
            <button type="button" @click="closeEditor()" class="rounded-lg p-1 text-ink/60 hover:text-ink focus-ring" aria-label="Close">
                <x-ui.icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <form id="lesson-form" class="flex flex-1 flex-col overflow-hidden" @submit.prevent="saveLesson($event)">
            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                {{-- Title --}}
                <div class="space-y-1.5">
                    <label for="lesson_title" class="block text-sm font-medium text-ink">Lesson title <span class="text-crimson">*</span></label>
                    <input id="lesson_title" type="text" name="title" x-model="lesson.title" required maxlength="200"
                           class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                           placeholder="e.g. What is public relations?">
                    <p class="text-sm text-crimson" x-show="errors.title" x-text="errors.title" x-cloak></p>
                </div>

                {{-- Type --}}
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-ink">Lesson type</label>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (LessonType::cases() as $type)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm transition-colors"
                                   :class="lesson.type === @js($type->value) ? 'border-crimson bg-crimson/5 text-crimson' : 'border-line text-ink/70 hover:bg-surface'">
                                <input type="radio" name="type" value="{{ $type->value }}" x-model="lesson.type" class="sr-only">
                                <x-ui.icon :name="$type->icon()" class="h-4 w-4" />
                                {{ $type->label() }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- VIDEO --}}
                <div x-show="lesson.type === 'video'" class="space-y-3" x-cloak>
                    <div class="flex gap-2">
                        <label class="flex-1 cursor-pointer rounded-xl border px-3 py-2 text-center text-sm"
                               :class="lesson.video_source === 'embed' ? 'border-crimson bg-crimson/5 text-crimson' : 'border-line text-ink/70'">
                            <input type="radio" name="video_source" value="embed" x-model="lesson.video_source" class="sr-only"> Embed (YouTube / Vimeo)
                        </label>
                        <label class="flex-1 cursor-pointer rounded-xl border px-3 py-2 text-center text-sm"
                               :class="lesson.video_source === 'upload' ? 'border-crimson bg-crimson/5 text-crimson' : 'border-line text-ink/70'">
                            <input type="radio" name="video_source" value="upload" x-model="lesson.video_source" class="sr-only"> Upload file
                        </label>
                    </div>

                    {{-- Embed --}}
                    <div x-show="lesson.video_source === 'embed'" class="space-y-2">
                        <label for="lesson_video_url" class="block text-sm font-medium text-ink">Video URL</label>
                        <input id="lesson_video_url" type="url" name="video_url" x-model="lesson.video_url"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                               placeholder="https://www.youtube.com/watch?v=…">
                        <p class="text-sm text-crimson" x-show="errors.video_url" x-text="errors.video_url" x-cloak></p>

                        {{-- Live preview --}}
                        <template x-if="videoEmbed(lesson.video_url)">
                            <div class="aspect-video overflow-hidden rounded-xl border border-line bg-ink/5">
                                <iframe :src="videoEmbed(lesson.video_url)" class="h-full w-full" title="Video preview" allowfullscreen></iframe>
                            </div>
                        </template>
                        <template x-if="lesson.video_url && !videoEmbed(lesson.video_url)">
                            <p class="text-xs text-ink/50">Paste a YouTube or Vimeo link to see a preview.</p>
                        </template>
                    </div>

                    {{-- Upload video --}}
                    <div x-show="lesson.video_source === 'upload'" x-cloak>
                        <x-courses.file-field :max-kb="$lessonMediaMaxKb" accept="video/mp4,video/webm,video/quicktime" />
                    </div>
                </div>

                {{-- TEXT (rich editor always in DOM for TinyMCE init) --}}
                <div x-show="lesson.type === 'text'" x-cloak>
                    <x-ui.rich-editor name="content_text" id="lesson_content_text" label="Lesson content" :height="280"
                                      placeholder="Write the lesson…" />
                </div>

                {{-- FILE types --}}
                <div x-show="['pdf','document','audio'].includes(lesson.type)" x-cloak>
                    <x-courses.file-field :max-kb="$lessonMediaMaxKb" />
                </div>

                {{-- EXTERNAL LINK --}}
                <div x-show="lesson.type === 'external_link'" class="space-y-1.5" x-cloak>
                    <label for="lesson_external_url" class="block text-sm font-medium text-ink">External URL</label>
                    <input id="lesson_external_url" type="url" name="external_url" x-model="lesson.external_url"
                           class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                           placeholder="https://…">
                    <p class="text-sm text-crimson" x-show="errors.external_url" x-text="errors.external_url" x-cloak></p>
                </div>

                {{-- Common: duration + free preview --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label for="lesson_duration" class="block text-sm font-medium text-ink">Duration (minutes)</label>
                        <input id="lesson_duration" type="number" min="0" name="duration_minutes" x-model="lesson.duration_minutes"
                               class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                               placeholder="e.g. 12">
                    </div>
                    <label class="flex items-center gap-3 self-end rounded-xl border border-line px-3 py-2.5">
                        <input type="checkbox" name="is_free_preview" value="1" x-model="lesson.is_free_preview"
                               class="rounded border-line text-crimson focus:ring-crimson">
                        <span class="text-sm text-ink">Free preview</span>
                    </label>
                </div>

                {{-- Generic error --}}
                <p class="rounded-xl bg-crimson/5 px-4 py-3 text-sm text-crimson" x-show="errors._" x-text="errors._" x-cloak></p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-4">
                <x-ui.button type="button" variant="ghost" @click="closeEditor()">Cancel</x-ui.button>
                <x-ui.button type="submit" ::disabled="saving">
                    <span x-show="!saving">Save lesson</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </x-ui.button>
            </div>
        </form>
    </div>
</div>
