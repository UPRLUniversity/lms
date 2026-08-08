@php
    /** @var \Illuminate\Support\Collection $certificates */
@endphp

<x-app-layout title="My Certificates">
    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">My Certificates</h2>
            <p class="mt-1 text-ink/70">Certificates you've earned by completing a course.</p>
        </div>

        @if ($certificates->isEmpty())
            <x-ui.empty-state
                icon="certificate"
                title="No certificates yet"
                description="Finish a course and your certificate of completion will appear here automatically.">
                <x-slot name="action">
                    <x-ui.button :href="route('learning.index')">My Learning</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                @foreach ($certificates as $certificate)
                    <x-ui.card>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-display text-lg font-semibold text-ink">{{ $certificate->course->title }}</p>
                                <p class="mt-1 text-xs text-ink/65">
                                    Issued {{ $certificate->issued_at->isoFormat('D MMM YYYY') }} · {{ $certificate->serial }}
                                </p>
                            </div>
                            @if ($certificate->isRevoked())
                                <x-ui.badge variant="crimson">Revoked</x-ui.badge>
                            @endif
                        </div>

                        @if ($certificate->gradeLine())
                            <p class="mt-3 text-sm font-medium text-gold-ink">Achieved: {{ $certificate->gradeLine() }}</p>
                        @endif

                        @if ($certificate->isRevoked())
                            <div class="mt-4">
                                <span class="text-sm text-ink/65">This certificate has been revoked.</span>
                            </div>
                        @else
                            <div
                                x-data="certificateStatus({ ready: {{ $certificate->isReady() ? 'true' : 'false' }}, statusUrl: '{{ route('certificates.status', $certificate) }}' })"
                                class="mt-4 flex flex-wrap items-center gap-2"
                            >
                                <template x-if="!ready">
                                    <x-ui.button size="sm" disabled>
                                        <x-ui.icon name="clock" class="h-4 w-4" /> Preparing…
                                    </x-ui.button>
                                </template>
                                <template x-if="ready">
                                    <x-ui.button size="sm" :href="route('certificates.download', $certificate)">
                                        <x-ui.icon name="download" class="h-4 w-4" /> Download
                                    </x-ui.button>
                                </template>

                                <x-ui.button size="sm" variant="ghost" :href="route('verify.show', $certificate->serial)" target="_blank" rel="noopener">
                                    Verify <x-ui.icon name="link" class="h-4 w-4" />
                                </x-ui.button>
                            </div>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
