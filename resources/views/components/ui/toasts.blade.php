{{--
    Global toast stack — the single, consistent feedback channel for the app.
    Anchored top-right, self-dismissing. Renders server flash (session('status'))
    on load AND listens for a `toast` window event dispatched from JS:
        window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }))
--}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const t = {
                id: Date.now() + Math.random(),
                message: detail?.message ?? String(detail ?? ''),
                type: detail?.type ?? 'success',
            };
            if (! t.message) return;
            this.toasts.push(t);
            setTimeout(() => this.remove(t.id), 4500);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
    }"
    x-init="@if (session('status')) add({ message: @js(session('status')), type: 'success' }); @endif"
    @toast.window="add($event.detail)"
    class="pointer-events-none fixed right-4 top-20 z-[100] flex w-full max-w-sm flex-col gap-2 sm:right-6"
    role="status" aria-live="polite" aria-atomic="true">

    <template x-for="t in toasts" :key="t.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-card px-4 py-3 shadow-lg ring-1 ring-black/5"
            :class="{
                'border-success/30': t.type === 'success',
                'border-crimson/30': t.type === 'error',
                'border-line': t.type === 'info',
            }">
            {{-- Status dot/icon --}}
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                  :class="{
                      'bg-success/10 text-success': t.type === 'success',
                      'bg-crimson/10 text-crimson': t.type === 'error',
                      'bg-ink/5 text-ink/60': t.type === 'info',
                  }">
                <svg x-show="t.type === 'success'" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4.5 12.75 6 6 9-13.5" /></svg>
                <svg x-show="t.type === 'error'" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12" /></svg>
                <svg x-show="t.type === 'info'" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01" /></svg>
            </span>

            <p class="flex-1 text-sm text-ink" x-text="t.message"></p>

            <button type="button" @click="remove(t.id)"
                    class="-mr-1 rounded-lg p-1 text-ink/40 hover:bg-ink/5 hover:text-ink focus-ring"
                    aria-label="Dismiss notification">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </template>
</div>
