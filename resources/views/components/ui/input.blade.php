@props([
    'type' => 'text',
    'invalid' => false,
])

<input
    type="{{ $type }}"
    @if ($invalid) aria-invalid="true" @endif
    {{ $attributes->merge([
        'class' => 'block w-full rounded-xl border-line bg-card text-ink shadow-sm placeholder:text-ink/40 '
            .'focus:border-crimson focus:ring-crimson '
            .($invalid ? 'border-crimson focus:border-crimson focus:ring-crimson' : ''),
    ]) }}
>
