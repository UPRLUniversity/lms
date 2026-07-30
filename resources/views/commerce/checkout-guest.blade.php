@php
    use App\Support\Money;

    /** @var \App\Models\Cart $cart */
    /** @var \App\Support\Commerce\CartTotals $totals */
    $courseLines = $totals->courseLines();
    $feeLines = $totals->feeLines();
@endphp

{{--
    The signed-out checkout entry.

    Deliberately NOT a redirect to /login: the buyer keeps their order in front of them
    while they sign in, and the intended URL is already remembered (CheckoutController),
    so logging in brings them straight back here with the cart merged in.
--}}
<x-public-layout title="Checkout" description="Sign in to complete your purchase.">
    <div class="mx-auto max-w-5xl px-6 py-10 lg:px-8">

        <nav class="mb-6 text-sm">
            <a href="{{ route('cart.index') }}" class="inline-flex items-center gap-1.5 rounded text-ink/60 hover:text-crimson focus-ring">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to cart
            </a>
        </nav>

        <h1 class="font-display text-3xl font-bold text-ink">Almost there</h1>
        <p class="mt-2 max-w-xl text-ink/70">
            Your order is ready. Sign in — or create an account in a minute — and we will bring you
            straight back here with everything still in your cart.
        </p>

        <div class="mt-8 grid gap-6 lg:grid-cols-5">

            {{-- Sign in / register. First on mobile: it is the only action on this page. --}}
            <div class="lg:col-span-3">
                <x-ui.card>
                    <h2 class="font-display text-lg font-semibold text-ink">Log in to continue</h2>
                    <p class="mt-1 text-sm text-ink/65">
                        An order has to belong to somebody — it is how you get your receipt, your access
                        and your certificate.
                    </p>

                    <div class="mt-5 space-y-2.5">
                        <x-ui.button size="lg" class="w-full" :href="route('login')">
                            <x-ui.icon name="lock" class="h-5 w-5" /> Log in and continue
                        </x-ui.button>
                        <x-ui.button size="lg" variant="secondary" class="w-full" :href="route('register')">
                            <x-ui.icon name="user-plus" class="h-5 w-5" /> Create an account
                        </x-ui.button>
                    </div>

                    <ul class="mt-6 space-y-2.5 border-t border-line pt-5 text-sm text-ink/70">
                        <li class="flex items-start gap-2.5">
                            <x-ui.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-success" stroke-width="2.5" />
                            Your cart survives the sign-in — nothing needs adding again.
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-ui.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-success" stroke-width="2.5" />
                            You will review the full order and choose how to pay on the next screen.
                        </li>
                        <li class="flex items-start gap-2.5">
                            <x-ui.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-success" stroke-width="2.5" />
                            Access opens the moment payment clears.
                        </li>
                    </ul>
                </x-ui.card>
            </div>

            {{-- Order summary — the same lines and arithmetic the real checkout will show. --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-line bg-surface/60 p-5 lg:sticky lg:top-24">
                    <h2 class="font-display text-lg font-semibold text-ink">Your order</h2>

                    <ul class="mt-4 space-y-3 border-b border-line pb-4">
                        @foreach ($courseLines as $line)
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <span class="min-w-0 text-ink/80">{{ $line->title }}</span>
                                <span class="shrink-0 font-medium text-ink">{{ $line->formattedAmount() }}</span>
                            </li>
                        @endforeach

                        @foreach ($feeLines as $line)
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <span class="min-w-0 text-ink/60">
                                    {{ $line->title }}
                                    <span class="block text-xs text-ink/45">Charged once per programme</span>
                                </span>
                                <span class="shrink-0 font-medium text-ink">{{ $line->formattedAmount() }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink/60">Subtotal</dt>
                            <dd class="font-medium text-ink">{{ $totals->formattedSubtotal() }}</dd>
                        </div>
                        @if ($totals->hasDiscount())
                            <div class="flex items-baseline justify-between gap-3 text-success">
                                <dt class="flex items-center gap-1.5">
                                    <x-ui.icon name="tag" class="h-4 w-4" /> {{ $totals->coupon->code }}
                                </dt>
                                <dd class="font-medium">{{ $totals->formattedDiscount() }}</dd>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between gap-3 border-t border-line pt-2">
                            <dt class="font-display text-base font-semibold text-ink">Grand total</dt>
                            <dd class="font-display text-2xl font-bold text-ink">{{ $totals->formattedTotal() }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs text-ink/55">
                        Prices in {{ Money::currency() }}. Entry fees, where they apply, are shown above and
                        charged only on your first purchase in a programme.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-public-layout>
