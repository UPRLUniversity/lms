@php
    $canManage = auth()->user()->can(\App\Enums\Permission::CertificatesManage->value);
@endphp

<x-app-layout title="Certificates">
    <div class="mx-auto max-w-6xl space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Certificate registry</h2>
                <p class="mt-1 text-ink/70">Every certificate issued, searchable by student, course or serial.</p>
            </div>
            <x-ui.button variant="secondary" :href="route('admin.certificate-templates.index')">
                <x-ui.icon name="cog" class="h-4 w-4" /> Templates
            </x-ui.button>
        </div>

        @if ($missing->isNotEmpty() && $canManage)
            <x-ui.card>
                <h3 class="font-display font-semibold text-ink">Completed, not yet issued</h3>
                <p class="mt-1 text-xs text-ink/60">Students who finished a course but have no certificate yet (a rare gap — issuance normally happens automatically).</p>
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($missing as $enrollment)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <div class="text-sm">
                                <span class="font-medium text-ink">{{ $enrollment->user->name }}</span>
                                <span class="text-ink/50"> · {{ $enrollment->course->title }}</span>
                            </div>
                            <form method="POST" action="{{ route('admin.certificates.issue') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $enrollment->user_id }}">
                                <input type="hidden" name="course_id" value="{{ $enrollment->course_id }}">
                                <x-ui.button type="submit" size="sm" variant="secondary">Issue certificate</x-ui.button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card :padding="false">
            <div class="border-b border-line px-5 py-4">
                <form method="GET" class="flex gap-2">
                    <x-ui.input name="search" value="{{ $search }}" placeholder="Search student, course or serial…" class="max-w-sm" />
                    <x-ui.button type="submit" variant="secondary">
                        <x-ui.icon name="search" class="h-4 w-4" />
                    </x-ui.button>
                </form>
            </div>

            @if ($certificates->isEmpty())
                <div class="px-5 py-10">
                    <x-ui.empty-state icon="certificate" title="No certificates found" description="Nothing matches that search yet." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="border-b border-line text-left text-xs font-semibold uppercase tracking-wide text-ink/50">
                                <th class="px-5 py-3.5">Student</th>
                                <th class="px-5 py-3.5">Course</th>
                                <th class="px-5 py-3.5">Serial</th>
                                <th class="px-5 py-3.5">Issued</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($certificates as $certificate)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-ink">{{ $certificate->user->name }}</p>
                                        <p class="text-xs text-ink/50">{{ $certificate->user->email }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-ink/80">{{ $certificate->course->title }}</td>
                                    <td class="px-5 py-4 font-mono text-xs text-ink/70">{{ $certificate->serial }}</td>
                                    <td class="px-5 py-4 text-ink/70">{{ $certificate->issued_at->isoFormat('D MMM YYYY') }}</td>
                                    <td class="px-5 py-4">
                                        @if ($certificate->isRevoked())
                                            <x-ui.badge variant="crimson">Revoked</x-ui.badge>
                                        @elseif (! $certificate->isReady())
                                            <x-ui.badge variant="neutral">Preparing…</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="success">Valid</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($certificate->isReady())
                                                <x-ui.button size="sm" variant="ghost" :href="route('certificates.download', $certificate)">
                                                    <x-ui.icon name="download" class="h-4 w-4" />
                                                </x-ui.button>
                                            @endif
                                            <x-ui.button size="sm" variant="ghost" :href="route('verify.show', $certificate->serial)" target="_blank" rel="noopener">
                                                <x-ui.icon name="link" class="h-4 w-4" />
                                            </x-ui.button>

                                            @if ($canManage)
                                                <form method="POST" action="{{ route('admin.certificates.reissue', $certificate) }}"
                                                      onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Re-issue this certificate?', text: 'The serial stays the same; the PDF is regenerated from the current template and grade.', danger: false, confirmText: 'Re-issue' }).then(ok => ok && this.submit());">
                                                    @csrf
                                                    <x-ui.button type="submit" size="sm" variant="ghost">
                                                        <x-ui.icon name="arrow-path" class="h-4 w-4" />
                                                    </x-ui.button>
                                                </form>

                                                @if ($certificate->isRevoked())
                                                    <form method="POST" action="{{ route('admin.certificates.restore', $certificate) }}">
                                                        @csrf
                                                        <x-ui.button type="submit" size="sm" variant="ghost" class="text-success">Restore</x-ui.button>
                                                    </form>
                                                @else
                                                    <button type="button" x-data @click="$dispatch('open-modal', 'revoke-{{ $certificate->public_id }}')"
                                                            class="inline-flex items-center gap-1.5 rounded-lg p-1.5 text-ink/40 hover:text-crimson focus-ring" aria-label="Revoke certificate">
                                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                                    </button>

                                                    <x-ui.modal :name="'revoke-'.$certificate->public_id" title="Revoke this certificate?">
                                                        <form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}" class="space-y-4">
                                                            @csrf
                                                            <p class="text-sm text-ink/70">
                                                                {{ $certificate->user->name }}'s certificate ({{ $certificate->serial }}) will show as
                                                                revoked on the public verification page. The reason is never shown publicly.
                                                            </p>
                                                            <div>
                                                                <label for="reason-{{ $certificate->public_id }}" class="sr-only">Reason</label>
                                                                <textarea id="reason-{{ $certificate->public_id }}" name="reason" rows="3" required
                                                                          class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson"
                                                                          placeholder="e.g. Academic integrity investigation."></textarea>
                                                            </div>
                                                            <div class="flex justify-end gap-2">
                                                                <x-ui.button type="button" variant="ghost" @click="$dispatch('close-modal', 'revoke-{{ $certificate->public_id }}')">Cancel</x-ui.button>
                                                                <x-ui.button type="submit" variant="danger">Revoke</x-ui.button>
                                                            </div>
                                                        </form>
                                                    </x-ui.modal>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    {{ $certificates->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
