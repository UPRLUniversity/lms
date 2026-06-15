@php
    use App\Services\Courses\BulkEnrollmentService as Bulk;

    $counts = $report['counts'];
    $badge = fn (string $problem) => $problem === Bulk::OK ? 'success' : 'crimson';
@endphp

<x-app-layout title="Preview import">
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Preview import</h2>
                <p class="mt-1 text-ink/70">Review every row below. Only the rows marked <span class="font-medium text-success">ready</span> will be imported.</p>
            </div>
            <x-ui.button variant="ghost" :href="route('enrollments.bulk.create')">
                <x-ui.icon name="arrow-left" class="h-5 w-5" /> Upload a different file
            </x-ui.button>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <x-ui.stat label="Total rows" :value="$counts['total']" icon="document-text" tone="neutral" />
            <x-ui.stat label="Ready to import" :value="$counts['valid']" icon="check" tone="success" />
            <x-ui.stat label="Will be skipped" :value="$counts['invalid']" icon="x" tone="crimson" />
        </div>

        @if ($queues)
            <div class="rounded-xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-ink/80">
                This file has more than 100 rows, so the import will run in the background once you confirm.
            </div>
        @endif

        {{-- Preview table --}}
        <x-ui.card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-xs uppercase tracking-wide text-ink/50">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Row</th>
                            <th scope="col" class="px-5 py-3 font-medium">Email</th>
                            <th scope="col" class="px-5 py-3 font-medium">Course</th>
                            <th scope="col" class="px-5 py-3 font-medium">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($report['rows'] as $row)
                            <tr class="hover:bg-surface/60 {{ $row['problem'] !== Bulk::OK ? 'bg-crimson/[0.02]' : '' }}">
                                <td class="px-5 py-3 text-ink/40">{{ $row['line'] }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-ink">{{ $row['email'] ?: '—' }}</span>
                                    @if ($row['user_name'])
                                        <span class="block text-xs text-ink/50">{{ $row['user_name'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-ink">{{ $row['course_code'] ?: '—' }}</span>
                                    @if ($row['course_title'])
                                        <span class="block text-xs text-ink/50">{{ $row['course_title'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <x-ui.badge :variant="$badge($row['problem'])">{{ Bulk::problemLabel($row['problem']) }}</x-ui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- Confirm --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink/60">
                {{ $counts['valid'] }} of {{ $counts['total'] }} rows will be enrolled.
            </p>
            @if ($counts['valid'] === 0)
                <span class="rounded-xl border border-line bg-surface px-4 py-2.5 text-sm text-ink/50">Nothing to import</span>
            @else
                <form method="POST" action="{{ route('enrollments.bulk.store') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-ui.button type="submit">
                        <x-ui.icon name="check" class="h-5 w-5" stroke-width="2.5" />
                        {{ $queues ? 'Queue import' : 'Import '.$counts['valid'].' '.\Illuminate\Support\Str::plural('student', $counts['valid']) }}
                    </x-ui.button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
