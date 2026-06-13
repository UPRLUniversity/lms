@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'required' => false,
    'id' => null,
])

@php
    $id = $id ?? $name;
    // $errors is shared on every web request; guard for isolated/non-web renders.
    $error = ($errors ?? null)?->first($name);
    $hintId = $hint ? "{$id}-hint" : null;
    $errorId = $error ? "{$id}-error" : null;
    $describedBy = trim(implode(' ', array_filter([$hintId, $errorId]))) ?: null;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">
        {{ $label }}
        @if ($required)
            <span class="text-crimson" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-ink/70">{{ $hint }}</p>
    @endif

    {{-- Caller may supply a custom control (select/textarea) in the slot; otherwise render a text input. --}}
    @if (trim($slot) !== '')
        {{ $slot }}
    @else
        <x-ui.input
            :type="$type"
            :id="$id"
            :name="$name"
            :required="$required"
            :invalid="(bool) $error"
            :aria-describedby="$describedBy"
            :attributes="$attributes->except('class')"
        />
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="text-sm text-crimson">{{ $error }}</p>
    @endif
</div>
