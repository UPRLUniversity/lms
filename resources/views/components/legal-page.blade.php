@props([
    'title',
    'updated' => null,
])

{{--
    Shared shell for the legal pages (Section 15).

    Uses the PUBLIC layout, not the app shell: these must be readable by someone who has
    not signed up yet — which is the whole point of linking them from the registration
    form and both footers.

    The content is placeholder wording with the real STRUCTURE in place, so the
    institution's counsel can replace the prose section by section without anyone
    touching routing, layout or navigation. The banner says so plainly, rather than
    letting placeholder text pass itself off as a policy someone might rely on.
--}}
<x-public-layout :title="$title" :description="$title.' — '.config('brand.university')">
    <div class="mx-auto max-w-3xl px-6 py-12 lg:py-16">
        <x-ui.breadcrumbs class="mb-6" :items="[
            ['label' => 'Home', 'href' => route('home')],
            ['label' => $title],
        ]" />

        <h1 class="font-display text-3xl font-bold text-ink sm:text-4xl">{{ $title }}</h1>

        @if ($updated)
            <p class="mt-2 text-sm text-ink/60">Last updated {{ $updated }}</p>
        @endif

        <div role="note" class="mt-6 rounded-xl border border-gold/40 bg-gold/10 px-4 py-3">
            <p class="text-sm text-gold-ink">
                <strong class="font-semibold">Placeholder.</strong>
                This page carries the structure of the final document. The binding wording is
                being prepared by {{ config('brand.university') }} and will replace the text
                below before launch.
            </p>
        </div>

        <x-ui.prose class="mt-8">
            {{ $slot }}
        </x-ui.prose>

        <p class="mt-10 border-t border-line pt-6 text-sm text-ink/60">
            Questions about this page? Write to
            <a href="mailto:{{ config('mail.support') }}" class="rounded font-medium text-crimson underline-offset-2 hover:underline focus-ring">{{ config('mail.support') }}</a>.
        </p>
    </div>
</x-public-layout>
