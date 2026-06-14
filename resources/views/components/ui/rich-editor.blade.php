@props([
    'name',
    'label' => null,
    'value' => '',
    'id' => null,
    'placeholder' => '',
    'height' => 320,
    'profile' => 'full',   // full | basic
    'hint' => null,
    'required' => false,
])

@php
    $id = $id ?? $name;
    $error = ($errors ?? null)?->first($name);
    $hintId = $hint ? "{$id}-hint" : null;
    $errorId = $error ? "{$id}-error" : null;
    $describedBy = trim(implode(' ', array_filter([$hintId, $errorId]))) ?: null;
    $profile = $profile === 'basic' ? 'basic' : 'full';
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-ink">
            {{ $label }}
            @if ($required)
                <span class="text-crimson" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-ink/70">{{ $hint }}</p>
    @endif

    {{-- Progressive enhancement: a usable textarea before/without JS; TinyMCE
         replaces it. The textarea stays in sync so normal form submits work. --}}
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="8"
        data-rich-editor
        data-profile="{{ $profile }}"
        data-height="{{ (int) $height }}"
        data-upload-url="{{ route('editor.upload') }}"
        data-csrf="{{ csrf_token() }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
    >{{ $value }}</textarea>

    @if ($error)
        <p id="{{ $errorId }}" class="text-sm text-crimson">{{ $error }}</p>
    @endif
</div>
