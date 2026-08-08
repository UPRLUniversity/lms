{{--
    A brand-artwork field: current asset preview, a replace input, and a "revert to
    packaged artwork" checkbox that only appears when there is an upload to revert.

    The preview sits on a checkerboard so a transparent PNG's edges are visible — a
    logo with a white box baked into it is otherwise invisible until it reaches a
    crimson background in production.
--}}
<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-medium text-ink">
        {{ $definition['label'] }}
    </label>

    @if ($hintId)
        <p id="{{ $hintId }}" class="text-xs text-ink/70">{{ $definition['help'] }}</p>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <div class="flex h-16 w-32 shrink-0 items-center justify-center rounded-xl border border-line p-2"
             style="background-image:linear-gradient(45deg,#f1efec 25%,transparent 25%,transparent 75%,#f1efec 75%),linear-gradient(45deg,#f1efec 25%,transparent 25%,transparent 75%,#f1efec 75%);background-size:12px 12px;background-position:0 0,6px 6px;">
            @if ($media)
                <img src="{{ $media->url }}" alt="Current {{ strtolower($definition['label']) }}" class="max-h-full max-w-full object-contain">
            @else
                <span class="text-[11px] font-medium uppercase tracking-wide text-ink/65">Packaged</span>
            @endif
        </div>

        <div class="min-w-0 flex-1 space-y-2">
            <input type="file"
                   id="{{ $name }}"
                   name="uploads[{{ $name }}]"
                   accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon"
                   @if ($hintId || $errorId) aria-describedby="{{ trim(($hintId ?? '').' '.($errorId ?? '')) }}" @endif
                   @if ($error) aria-invalid="true" @endif
                   class="block w-full text-sm text-ink/70 file:mr-3 file:rounded-lg file:border-0 file:bg-crimson file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-crimson-dark focus-ring rounded-xl">

            @if ($media)
                <label class="flex items-center gap-2 text-sm text-ink/70">
                    <input type="checkbox"
                           name="clear[{{ $name }}]"
                           value="1"
                           class="h-4 w-4 rounded border-line text-crimson focus:ring-crimson focus-ring">
                    Remove and fall back to the packaged artwork
                </label>
                <p class="text-xs text-ink/65">{{ $media->original_name }}</p>
            @endif
        </div>
    </div>

    @if ($error)
        <p id="{{ $errorId }}" class="text-sm text-crimson">{{ $error }}</p>
    @endif
</div>
