@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-xl border-line bg-card text-ink shadow-sm placeholder:text-ink/40 focus:border-crimson focus:ring-crimson disabled:opacity-50']) }}>
