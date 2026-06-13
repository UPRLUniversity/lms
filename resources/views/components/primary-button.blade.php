<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-crimson border border-transparent rounded-xl font-medium text-sm text-white hover:bg-crimson-dark focus-ring disabled:opacity-50 transition-colors']) }}>
    {{ $slot }}
</button>
