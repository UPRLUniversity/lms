@php use Illuminate\Support\Str; @endphp

<x-app-layout title="Orders">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h2 class="font-display text-2xl font-semibold text-ink">Orders</h2>
            <p class="mt-1 text-ink/70">Every purchase, and the two things only a person can decide: whether a transfer arrived, and whether to refund.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat label="Revenue (paid)" :value="$paidTotal" icon="banknote" tone="success" />
            <x-ui.stat label="Paid orders" :value="$paidCount" icon="receipt" />
            <x-ui.stat label="Awaiting payment" :value="$awaitingCount" icon="clock" tone="gold" />
        </div>

        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-col gap-3 rounded-2xl border border-line bg-card p-4 shadow-sm sm:flex-row">
            <div class="relative flex-1">
                <label for="q" class="sr-only">Search orders</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink/40">
                    <x-ui.icon name="search" class="h-5 w-5" />
                </span>
                <x-ui.input id="q" name="q" type="search" :value="$filters['search']"
                            placeholder="Reference, name or email…" class="pl-10" />
            </div>
            <div class="sm:w-56">
                <label for="status" class="sr-only">Status</label>
                <select id="status" name="status"
                        class="block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit" variant="secondary">Filter</x-ui.button>
        </form>

        @if ($orders->isEmpty())
            <x-ui.empty-state icon="receipt" title="No orders yet"
                              description="Purchases will appear here as students check out." />
        @else
            {{-- Cards, not a table: this has to stay readable at 375px, and an order row
                 carries five different things a table would squash. --}}
            <div class="space-y-3">
                @foreach ($orders as $order)
                    @php $courseCount = $order->courseItems()->count(); @endphp
                    <x-ui.card :padding="false">
                        <div class="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-5">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.orders.show', $order) }}"
                                       class="font-mono text-sm font-semibold text-ink hover:text-crimson focus-ring rounded">
                                        {{ $order->shortReference() }}
                                    </a>
                                    <x-ui.badge :variant="$order->status->badge()">{{ $order->status->label() }}</x-ui.badge>
                                    @if ($order->coupon_code)
                                        <x-ui.badge variant="gold">{{ $order->coupon_code }}</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-1 truncate text-sm text-ink/75">
                                    {{ $order->user?->name ?? 'Deleted user' }}
                                    <span class="text-ink/45">· {{ $order->user?->email }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-ink/50">
                                    {{ $courseCount }} {{ Str::plural('course', $courseCount) }}
                                    · {{ $order->created_at->isoFormat('D MMM YYYY, HH:mm') }}
                                    @if ($order->payment_method_key)
                                        · {{ Str::headline($order->payment_method_key) }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <span class="font-display text-lg font-bold text-ink">{{ $order->formattedTotal() }}</span>

                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @can('manage', $order)
                                        @if (! $order->isPaid() && $order->status->isOpen())
                                            <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}"
                                                  x-data
                                                  @submit.prevent="if (await window.uprlConfirm({ title: 'Confirm this payment?', text: 'The buyer will be enrolled and sent a receipt.', confirmText: 'Yes, confirm' })) $el.submit()">
                                                @csrf
                                                <x-ui.button size="sm" type="submit">Mark paid</x-ui.button>
                                            </form>
                                        @endif
                                    @endcan
                                    <x-ui.button size="sm" variant="ghost" :href="route('admin.orders.show', $order)">View</x-ui.button>
                                </div>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{ $orders->links() }}
        @endif
    </div>
</x-app-layout>
