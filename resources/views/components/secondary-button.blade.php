<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-card border border-line rounded-xl font-medium text-sm text-ink shadow-sm hover:bg-surface focus-ring disabled:opacity-50 transition-colors']) }}>
    {{ $slot }}
</button>
