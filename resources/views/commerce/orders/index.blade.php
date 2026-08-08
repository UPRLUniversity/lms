@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Your orders">
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Your orders</h2>
            <p class="mt-1 text-ink/70">Every purchase you have made, with its receipt.</p>
        </div>

        @if ($orders->isEmpty())
            <x-ui.empty-state
                icon="receipt"
                title="No orders yet"
                description="When you buy a course it will appear here with its receipt.">
                <x-slot name="action">
                    <x-ui.button :href="route('catalogue.index')">Browse courses</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            {{-- A list of cards rather than a table: this has to read well at 375px, and
                 a 5-column table never does. --}}
            <div class="space-y-3">
                @foreach ($orders as $order)
                    @php $courseCount = $order->courseItems()->count(); @endphp
                    <a href="{{ route('orders.show', $order) }}"
                       class="block rounded-2xl border border-line bg-card p-4 shadow-sm transition hover:border-crimson/30 hover:shadow-md focus-ring sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-semibold text-ink">{{ $order->shortReference() }}</span>
                                    <x-ui.badge :variant="$order->status->badge()">{{ $order->status->label() }}</x-ui.badge>
                                </div>
                                <p class="mt-1 text-sm text-ink/70">
                                    {{ $courseCount }} {{ Str::plural('course', $courseCount) }}
                                    @if ($order->feeItems()->isNotEmpty())
                                        · includes programme fees
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-ink/65">
                                    {{ $order->created_at->isoFormat('D MMM YYYY, HH:mm') }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="font-display text-lg font-bold text-ink">{{ $order->formattedTotal() }}</span>
                                @if ($order->status->isOpen())
                                    <span class="mt-0.5 block text-xs font-medium text-gold-ink">Action needed</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{ $orders->links() }}
        @endif
    </div>
</x-app-layout>
