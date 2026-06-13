@props([
    'name' => 'password',
    'id' => null,
    'invalid' => false,
])

@php
    $id = $id ?? $name;
@endphp

{{-- Password input with an accessible show/hide toggle. Class on the tag styles
     the wrapper; all other attributes (autocomplete, required, …) pass to the input. --}}
<div {{ $attributes->only('class')->merge(['class' => 'relative']) }} x-data="{ show: false }">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="password"
        x-bind:type="show ? 'text' : 'password'"
        @if ($invalid) aria-invalid="true" @endif
        {{ $attributes->except('class')->merge([
            'class' => 'block w-full rounded-xl border-line bg-card text-ink shadow-sm pr-11 placeholder:text-ink/40 '
                .'focus:border-crimson focus:ring-crimson '
                .($invalid ? 'border-crimson' : ''),
        ]) }}
    >

    <button
        type="button"
        x-on:click="show = ! show"
        x-bind:aria-label="show ? 'Hide password' : 'Show password'"
        x-bind:aria-pressed="show.toString()"
        class="absolute inset-y-0 right-0 flex items-center rounded-r-xl px-3 text-ink/50 hover:text-ink focus-ring"
        tabindex="0"
    >
        <x-ui.icon name="eye" class="h-5 w-5" x-show="! show" />
        <x-ui.icon name="eye-off" class="h-5 w-5" x-show="show" x-cloak />
    </button>
</div>
