@php
    /** @var \App\Support\Commerce\CartTotals $totals */
    /** @var \Illuminate\Support\Collection $methods */
    $billing = old();
    $selected = old('payment_method', $methods->first()?->key);
@endphp

<x-app-layout title="Checkout">
    <div class="mx-auto max-w-6xl">
        <nav class="mb-6 text-sm">
            <a href="{{ route('cart.index') }}" class="inline-flex items-center gap-1.5 text-ink/60 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Back to cart
            </a>
        </nav>

        <h2 class="font-display text-2xl font-semibold text-ink">Checkout</h2>
        <p class="mt-1 text-ink/70">Review your order, then choose how you would like to pay.</p>

        @if ($methods->isEmpty())
            <div class="mt-6 rounded-2xl border border-crimson/30 bg-crimson/5 p-5">
                <h3 class="font-display font-semibold text-ink">No payment method is available</h3>
                <p class="mt-1 text-sm text-ink/70">
                    An administrator needs to switch one on before orders can be taken. Please try again shortly.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('checkout.store') }}" class="mt-6 grid gap-6 lg:grid-cols-5">
                @csrf

                {{-- Order summary first on mobile: people want to see what they are buying
                     before being asked for details. --}}
                <div class="lg:order-2 lg:col-span-2">
                    <div class="rounded-2xl border border-line bg-surface/60 p-5 lg:sticky lg:top-24">
                        <h3 class="font-display text-lg font-semibold text-ink">Order details</h3>

                        <ul class="mt-4 space-y-3 border-b border-line pb-4">
                            @foreach ($totals->courseLines() as $line)
                                <li class="flex items-start justify-between gap-3 text-sm">
                                    <span class="min-w-0 text-ink/80">{{ $line->title }}</span>
                                    <span class="shrink-0 font-medium text-ink">{{ $line->formattedAmount() }}</span>
                                </li>
                            @endforeach

                            @foreach ($totals->feeLines() as $line)
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

                        @if ($totals->isFree())
                            <p class="mt-3 rounded-xl bg-success/10 px-3 py-2 text-xs text-success">
                                Nothing to pay — we will confirm your place straight away.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Billing + payment --}}
                <div class="space-y-6 lg:order-1 lg:col-span-3">

                    <x-ui.card>
                        <h3 class="font-display text-lg font-semibold text-ink">Your details</h3>
                        <p class="mt-1 text-xs text-ink/60">Used for your receipt. We already have your account details — add anything else you would like on record.</p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <x-ui.field name="first_name" label="First name" :value="old('first_name', explode(' ', $user->name)[0] ?? '')" />
                            <x-ui.field name="last_name" label="Last name" :value="old('last_name', collect(explode(' ', $user->name))->slice(1)->implode(' '))" />
                            <x-ui.field class="sm:col-span-2" name="email" label="Email" type="email" :value="old('email', $user->email)" />
                            <x-ui.field name="phone" label="Phone" hint="Optional" :value="old('phone')" />
                            <x-ui.field name="country" label="Country" hint="Optional" :value="old('country', 'Nigeria')" />
                            <x-ui.field name="city" label="City" hint="Optional" :value="old('city')" />
                            <x-ui.field name="address" label="Address" hint="Optional" :value="old('address')" />
                        </div>
                    </x-ui.card>

                    <x-ui.card>
                        <h3 class="font-display text-lg font-semibold text-ink">Payment method</h3>

                        @if ($totals->isFree())
                            <p class="mt-2 text-sm text-ink/70">
                                This order is fully covered, so no payment is needed.
                            </p>
                            <input type="hidden" name="payment_method" value="{{ $methods->first()->key }}">
                        @else
                            <div class="mt-3 space-y-2.5" role="radiogroup" aria-label="Payment method">
                                @foreach ($methods as $method)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition focus-within:ring-2 focus-within:ring-crimson
                                                  {{ $selected === $method->key ? 'border-crimson bg-crimson/5' : 'border-line hover:bg-surface/60' }}">
                                        <input type="radio" name="payment_method" value="{{ $method->key }}"
                                               class="mt-0.5 border-line text-crimson focus:ring-crimson"
                                               @checked($selected === $method->key)>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium text-ink">{{ $method->label }}</span>
                                                @if ($method->supportsSubscriptions())
                                                    <x-ui.badge variant="success">Supports subscriptions</x-ui.badge>
                                                @endif
                                                @if (! $method->isLive())
                                                    <x-ui.badge>Test mode</x-ui.badge>
                                                @endif
                                            </span>
                                            @if ($method->key === 'bank_transfer')
                                                <span class="mt-0.5 block text-xs text-ink/60">
                                                    You will see our account details next. Access opens once we confirm your transfer.
                                                </span>
                                            @elseif ($method->key === 'sandbox')
                                                <span class="mt-0.5 block text-xs text-ink/60">
                                                    Test gateway — completes instantly without taking any money.
                                                </span>
                                            @else
                                                <span class="mt-0.5 block text-xs text-ink/60">
                                                    You will be taken to {{ $method->label }} to pay securely, then returned here.
                                                </span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        @endif

                        <label class="mt-5 flex items-start gap-3 border-t border-line pt-5">
                            <input type="checkbox" name="terms" value="1" @checked(old('terms'))
                                   class="mt-0.5 rounded border-line text-crimson focus:ring-crimson">
                            <span class="text-sm text-ink/75">
                                I agree to the terms of use and privacy policy.
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('terms')" class="mt-2" />

                        <div class="mt-5">
                            <x-ui.button type="submit" class="w-full sm:w-auto">
                                {{ $totals->isFree() ? 'Confirm my place' : 'Pay '.$totals->formattedTotal() }}
                            </x-ui.button>
                        </div>
                    </x-ui.card>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
