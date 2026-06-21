@php
    use App\Enums\LessonType;
    use App\Services\Media\PrivateFileService;

    /** @var \App\Models\Lesson $lesson */
    /** @var \App\Models\Course $course */

    $file = $lesson->file();
    $resources = $lesson->resources();
    // Short-lived signed URL for private media (pdf/audio/uploaded video). Inline
    // players/iframes can't carry the session cookie reliably, so a signature gates them.
    $signedUrl = $file ? app(PrivateFileService::class)->temporaryUrl($file, 120) : null;
    $embed = $lesson->videoEmbedUrl();
@endphp

<div class="mx-auto w-full max-w-4xl">
    {{-- Lesson heading --}}
    <div class="mb-6">
        <p class="text-xs font-medium uppercase tracking-wide text-crimson">
            {{ $lesson->module->title }}
        </p>
        <h2 class="mt-1 font-display text-2xl font-semibold leading-tight text-ink sm:text-3xl">{{ $lesson->title }}</h2>
    </div>

    {{-- Content by type --}}
    @switch($lesson->type)
        @case(LessonType::Video)
            @if ($lesson->isUploadedVideo() && $signedUrl)
                <div class="overflow-hidden rounded-2xl border border-line bg-ink shadow-sm">
                    <video id="lesson-video" controls playsinline preload="metadata"
                           class="aspect-video w-full bg-ink">
                        <source src="{{ $signedUrl }}" type="{{ $file->mime ?? 'video/mp4' }}">
                        Your browser doesn't support embedded video.
                    </video>
                </div>
            @elseif ($embed)
                <div class="aspect-video overflow-hidden rounded-2xl border border-line bg-ink shadow-sm">
                    <iframe src="{{ $embed }}" title="{{ $lesson->title }}" class="h-full w-full"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
            @else
                <x-ui.empty-state icon="play" title="Video unavailable"
                    description="This video lesson hasn't been set up yet." />
            @endif
            @break

        @case(LessonType::Text)
            @if ($lesson->content_text)
                <article class="rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
                    <x-ui.prose :html="$lesson->content_text" class="mx-auto" />
                </article>
            @else
                <x-ui.empty-state icon="document-text" title="Nothing to read yet"
                    description="This lesson's text hasn't been written." />
            @endif
            @break

        @case(LessonType::Pdf)
            @if ($signedUrl)
                <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                        <p class="flex min-w-0 items-center gap-2 text-sm font-medium text-ink">
                            <x-ui.icon name="document" class="h-5 w-5 shrink-0 text-crimson" />
                            <span class="truncate">{{ $file->original_name }}</span>
                        </p>
                        <x-ui.button size="sm" variant="secondary" :href="route('media.download', $file)">
                            <x-ui.icon name="download" class="h-4 w-4" /> Download
                        </x-ui.button>
                    </div>
                    <iframe src="{{ $signedUrl }}#view=FitH" title="{{ $file->original_name }}"
                            class="h-[70vh] w-full bg-ink/5" loading="lazy"></iframe>
                </div>
            @else
                <x-ui.empty-state icon="document" title="No PDF attached"
                    description="This lesson's document hasn't been uploaded." />
            @endif
            @break

        @case(LessonType::Audio)
            @if ($signedUrl)
                <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                            <x-ui.icon name="audio" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $file->original_name }}</p>
                            <p class="text-xs text-ink/50">Audio lesson</p>
                        </div>
                    </div>
                    <audio controls preload="metadata" class="mt-4 w-full">
                        <source src="{{ $signedUrl }}" type="{{ $file->mime ?? 'audio/mpeg' }}">
                        Your browser doesn't support audio playback.
                    </audio>
                    <div class="mt-3">
                        <x-ui.button size="sm" variant="ghost" :href="route('media.download', $file)">
                            <x-ui.icon name="download" class="h-4 w-4" /> Download audio
                        </x-ui.button>
                    </div>
                </div>
            @else
                <x-ui.empty-state icon="audio" title="No audio attached"
                    description="This lesson's audio hasn't been uploaded." />
            @endif
            @break

        @case(LessonType::Document)
            @if ($file)
                <div class="flex flex-col items-start gap-4 rounded-2xl border border-line bg-card p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                            <x-ui.icon name="document" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink">{{ $file->original_name }}</p>
                            <p class="text-xs text-ink/50">Downloadable document</p>
                        </div>
                    </div>
                    <x-ui.button :href="route('media.download', $file)" class="shrink-0">
                        <x-ui.icon name="download" class="h-5 w-5" /> Download
                    </x-ui.button>
                </div>
            @else
                <x-ui.empty-state icon="document" title="No document attached"
                    description="This lesson's document hasn't been uploaded." />
            @endif
            @break

        @case(LessonType::ExternalLink)
            <div class="rounded-2xl border border-line bg-card p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-crimson/10 text-crimson">
                        <x-ui.icon name="link" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-medium text-ink">External resource</p>
                        <p class="mt-0.5 break-all text-sm text-ink/60">{{ $lesson->external_url }}</p>
                    </div>
                </div>
                @if ($lesson->external_url)
                    <div class="mt-5">
                        <x-ui.button :href="$lesson->external_url" target="_blank" rel="noopener noreferrer">
                            Open resource <x-ui.icon name="arrow-right" class="h-5 w-5" />
                        </x-ui.button>
                    </div>
                    <p class="mt-2 text-xs text-ink/50">Opens in a new tab. Return here and mark the lesson complete when you're done.</p>
                @endif
            </div>
            @break
    @endswitch

    {{-- Lesson resources --}}
    @if ($resources->isNotEmpty())
        <section class="mt-8" aria-labelledby="resources-heading">
            <h3 id="resources-heading" class="font-display text-sm font-semibold uppercase tracking-wide text-ink/60">Resources</h3>
            <ul class="mt-3 space-y-2">
                @foreach ($resources as $resource)
                    <li>
                        <a href="{{ route('media.download', $resource) }}"
                           class="group flex items-center gap-3 rounded-xl border border-line bg-card px-4 py-3 text-sm shadow-sm transition hover:border-crimson/40 hover:bg-surface focus-ring">
                            <x-ui.icon name="document" class="h-5 w-5 shrink-0 text-ink/40 group-hover:text-crimson" />
                            <span class="min-w-0 flex-1 truncate font-medium text-ink">{{ $resource->original_name }}</span>
                            <x-ui.icon name="download" class="h-4 w-4 shrink-0 text-ink/40 group-hover:text-crimson" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
