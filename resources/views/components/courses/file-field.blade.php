@props([
    'maxKb' => 25600,
    'accept' => null,
])

@php $maxMb = round($maxKb / 1024, $maxKb % 1024 === 0 ? 0 : 1); @endphp

<div class="space-y-2">
    <label class="block text-sm font-medium text-ink">File</label>

    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-line bg-surface/40 px-4 py-4 hover:bg-surface focus-within:ring-2 focus-within:ring-crimson">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-crimson/10 text-crimson">
            <x-ui.icon name="document" class="h-5 w-5" />
        </span>
        <span class="min-w-0">
            <span class="block text-sm font-medium text-ink" x-text="fileLabel || 'Choose a file to upload'">Choose a file to upload</span>
            <span class="block text-xs text-ink/50">Up to {{ $maxMb }}MB.</span>
        </span>
        <input type="file" name="file" @if ($accept) accept="{{ $accept }}" @endif class="sr-only"
               @change="fileLabel = $event.target.files[0]?.name ?? ''">
    </label>

    {{-- Existing file (edit mode) --}}
    <template x-if="lesson.file && !fileLabel">
        <p class="text-xs text-ink/60">
            Current file: <span class="font-medium text-ink" x-text="lesson.file.name"></span>. Choose a new file to replace it.
        </p>
    </template>

    <p class="text-sm text-crimson" x-show="errors.file" x-text="errors.file" x-cloak></p>
</div>
