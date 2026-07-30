@php
    use App\Enums\OrderStatus;

    /** @var \App\Models\Order $order */
    /** @var \App\Models\PaymentMethod|null $method */
    $courseItems = $order->courseItems();
    $feeItems = $order->feeItems();
@endphp

<x-app-layout :title="'Order '.$order->shortReference()">
    <div class="mx-auto max-w-3xl space-y-6">
        <nav class="text-sm">
            <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-ink/60 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Your orders
            </a>
        </nav>

        {{-- Status banner: the first thing the buyer needs to know. --}}
        @if ($order->isPaid())
            <div class="flex items-start gap-3 rounded-2xl border border-success/25 bg-success/8 p-5">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success/15 text-success">
                    <x-ui.icon name="check" class="h-5 w-5" stroke-width="2.5" />
                </span>
                <div>
                    <h2 class="font-display text-lg font-semibold text-ink">Payment received</h2>
                    <p class="mt-0.5 text-sm text-ink/70">
                        Paid {{ $order->paid_at?->isoFormat('D MMMM YYYY, HH:mm') }}.
                        {{ $courseItems->count() === 1 ? 'Your course is' : 'Your courses are' }} ready.
                    </p>
                </div>
            </div>
        @elseif ($order->status === OrderStatus::AwaitingPayment)
            <div class="rounded-2xl border border-gold/30 bg-gold/8 p-5">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gold/20 text-gold-ink">
                        <x-ui.icon name="clock" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-lg font-semibold text-ink">Awaiting your payment</h2>
                        <p class="mt-0.5 text-sm text-ink/70">
                            Transfer <strong class="font-semibold text-ink">{{ $order->formattedTotal() }}</strong> and quote
                            reference <strong class="font-mono font-semibold text-ink">{{ $order->shortReference() }}</strong>.
                            We will open your access as soon as it arrives.
                        </p>
                    </div>
                </div>

                @if ($method?->instructions)
                    <div class="mt-4 border-t border-gold/20 pt-4">
                        <x-ui.prose :html="$method->instructions" />
                    </div>
                @endif
            </div>
        @elseif ($order->status === OrderStatus::Refunded)
            <div class="rounded-2xl border border-line bg-surface p-5">
                <h2 class="font-display text-lg font-semibold text-ink">Refunded</h2>
                <p class="mt-0.5 text-sm text-ink/70">This order was refunded. Access to its courses has been withdrawn.</p>
            </div>
        @else
            <div class="rounded-2xl border border-line bg-surface p-5">
                <h2 class="font-display text-lg font-semibold text-ink">{{ $order->status->label() }}</h2>
                <p class="mt-0.5 text-sm text-ink/70">
                    @if ($order->status === OrderStatus::Failed)
                        That payment did not go through. Nothing was charged — you can add the courses to your cart and try again.
                    @else
                        We are still confirming this payment. Refresh in a moment.
                    @endif
                </p>
            </div>
        @endif

        {{-- Receipt --}}
        <x-ui.card :padding="false">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h3 class="font-display text-lg font-semibold text-ink">Receipt</h3>
                    <p class="mt-0.5 text-xs text-ink/55">
                        Reference <span class="font-mono">{{ $order->shortReference() }}</span>
                        · {{ $order->created_at->isoFormat('D MMM YYYY') }}
                    </p>
                </div>
                <x-ui.badge :variant="$order->status->badge()">{{ $order->status->label() }}</x-ui.badge>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($courseItems as $item)
                    <li class="flex items-start justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            {{-- The title is the snapshot taken at purchase; the link only
                                 exists while the course still does. --}}
                            @if ($item->course)
                                <a href="{{ route('catalogue.show', $item->course) }}"
                                   class="font-medium text-ink hover:text-crimson focus-ring rounded">{{ $item->title }}</a>
                            @else
                                <span class="font-medium text-ink">{{ $item->title }}</span>
                            @endif

                            @if ($order->isPaid() && $item->course)
                                <a href="{{ route('learn.resume', $item->course) }}"
                                   class="mt-0.5 flex items-center gap-1 text-xs font-medium text-crimson hover:underline focus-ring rounded">
                                    <x-ui.icon name="play" class="h-3.5 w-3.5" /> Start learning
                                </a>
                            @endif
                        </div>
                        <span class="shrink-0 font-medium text-ink">{{ $item->formattedLineTotal() }}</span>
                    </li>
                @endforeach

                @foreach ($feeItems as $item)
                    <li class="flex items-start justify-between gap-3 bg-surface/50 px-5 py-3.5">
                        <div class="min-w-0">
                            <span class="text-ink/75">{{ $item->title }}</span>
                            <span class="block text-xs text-ink/45">One-off programme fee</span>
                        </div>
                        <span class="shrink-0 font-medium text-ink">{{ $item->formattedLineTotal() }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="space-y-2 border-t border-line px-5 py-4 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-ink/60">Subtotal</dt>
                    <dd class="text-ink">{{ \App\Support\Money::format($order->subtotal) }}</dd>
                </div>
                @if ((float) $order->discount_total > 0)
                    <div class="flex items-baseline justify-between gap-3 text-success">
                        <dt>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</dt>
                        <dd>−{{ \App\Support\Money::format($order->discount_total) }}</dd>
                    </div>
                @endif
                <div class="flex items-baseline justify-between gap-3 border-t border-line pt-2">
                    <dt class="font-display font-semibold text-ink">Total</dt>
                    <dd class="font-display text-lg font-bold text-ink">{{ $order->formattedTotal() }}</dd>
                </div>
            </dl>
        </x-ui.card>

        @if ($order->isPaid())
            <div class="text-center">
                <x-ui.button variant="secondary" :href="route('learning.index')">Go to My Learning</x-ui.button>
            </div>
        @endif
    </div>
</x-app-layout>
