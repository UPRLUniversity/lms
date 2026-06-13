@props([
    'name',
    'title' => null,
    'show' => false,
    'maxWidth' => 'lg',
])

@php
    $maxWidth = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth];

    $titleId = $title ? "{$name}-modal-title" : null;
@endphp

{{--
    Accessible dialog built on the same Alpine focus-trap as Breeze's modal,
    so the open-modal / close-modal window events drive it:
        $dispatch('open-modal', '{{ $name }}')
        $dispatch('close-modal', '{{ $name }}')
--}}
<div
    x-data="{
        show: @js($show),
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) - 1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            setTimeout(() => firstFocusable()?.focus(), 100);
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
    style="display: {{ $show ? 'block' : 'none' }};"
    x-cloak
>
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-ink/60"></div>
    </div>

    <div
        x-show="show"
        role="dialog"
        aria-modal="true"
        @if ($titleId) aria-labelledby="{{ $titleId }}" @endif
        class="relative mb-6 transform overflow-hidden rounded-xl bg-card shadow-xl transition-all sm:mx-auto sm:w-full {{ $maxWidth }}"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        @if ($title)
            <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
                <h2 id="{{ $titleId }}" class="font-display text-lg font-semibold text-ink">{{ $title }}</h2>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1 text-ink/70 hover:text-ink focus-ring" aria-label="Close dialog">
                    <x-ui.icon name="x" class="h-5 w-5" />
                </button>
            </div>
        @endif

        <div class="px-6 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex justify-end gap-3 border-t border-line bg-surface/60 px-6 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
