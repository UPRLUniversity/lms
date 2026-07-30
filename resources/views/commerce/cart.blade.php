@php
    use App\Support\Money;

    /** @var \App\Models\Cart $cart */
    /** @var \App\Support\Commerce\CartTotals $totals */
    $courseLines = $totals->courseLines();
    $feeLines = $totals->feeLines();
    $count = $courseLines->count();
@endphp

<x-public-layout title="Your cart" description="Review the courses in your cart before checking out.">
    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">

        <nav class="mb-6 text-sm">
            <a href="{{ route('catalogue.index') }}" class="inline-flex items-center gap-1.5 text-ink/60 hover:text-crimson focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Keep browsing
            </a>
        </nav>

        <h1 class="font-display text-3xl font-bold text-ink">
            {{ $count === 0 ? 'Your cart' : $count.' '.Str::plural('course', $count).' in your cart' }}
        </h1>

        @if ($pruned > 0)
            <p class="mt-3 rounded-xl border border-line bg-success/5 px-4 py-3 text-sm text-ink/75">
                {{ $pruned === 1 ? 'One course was' : $pruned.' courses were' }} removed because you already have access.
            </p>
        @endif

        @if ($totals->isEmpty())
            <div class="mt-8">
                <x-ui.empty-state
                    icon="shopping-cart"
                    title="Nothing here yet"
                    description="Browse the catalogue and add a course to get started.">
                    <x-slot name="action">
                        <x-ui.button :href="route('catalogue.index')">Browse courses</x-ui.button>
                    </x-slot>
                </x-ui.empty-state>
            </div>
        @else
            {{-- Mobile-first: single column, summary below. Two columns from lg. --}}
            <div class="mt-8 grid gap-8 lg:grid-cols-3">

                {{-- Lines --}}
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-2xl border border-line bg-card shadow-sm">
                        <ul class="divide-y divide-line">
                            @foreach ($courseLines as $line)
                                @php
                                    $course = $line->course;
                                    $item = $cart->items->firstWhere('course_id', $course->id);
                                @endphp
                                <li class="flex gap-4 p-4 sm:p-5">
                                    <a href="{{ route('catalogue.show', $course) }}"
                                       class="relative hidden aspect-[16/9] w-28 shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-crimson to-crimson-dark focus-ring sm:block">
                                        @if ($course->coverUrl())
                                            <img src="{{ $course->coverUrl() }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <span class="absolute inset-0 flex items-center justify-center font-display text-sm font-bold text-white/85">
                                                {{ $course->code }}
                                            </span>
                                        @endif
                                    </a>

                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('catalogue.show', $course) }}"
                                           class="font-display font-semibold leading-snug text-ink hover:text-crimson focus-ring rounded">
                                            {{ $course->title }}
                                        </a>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink/55">
                                            <span>{{ $course->level->label() }}</span>
                                            @foreach ($course->programmeParts as $part)
                                                <span aria-hidden="true">·</span>
                                                <span>{{ $part->programme->code }} {{ $part->name }}</span>
                                            @endforeach
                                        </p>

                                        <div class="mt-2 flex items-center justify-between gap-3 sm:justify-start sm:gap-4">
                                            <span class="font-semibold text-ink sm:hidden">{{ $line->formattedAmount() }}</span>
                                            @if ($item)
                                                <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded text-xs font-medium text-ink/50 hover:text-crimson focus-ring">
                                                        Remove
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="hidden shrink-0 text-right sm:block">
                                        <span class="font-semibold text-ink">{{ $line->formattedAmount() }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        {{-- Programme entry fees, explained. Without this panel a one-off
                             ₦45,000 appearing beside a ₦7,000 paper looks like a mistake. --}}
                        @if ($feeLines->isNotEmpty())
                            <div class="border-t-2 border-dashed border-line bg-surface/60 p-4 sm:p-5">
                                <h2 class="flex items-center gap-2 font-display text-sm font-semibold text-ink">
                                    <x-ui.icon name="academic-cap" class="h-4 w-4 text-gold-ink" />
                                    One-off programme fees
                                </h2>
                                <p class="mt-1 text-xs text-ink/60">
                                    Charged once when you join a programme — never again on later papers.
                                </p>
                                <ul class="mt-3 space-y-1.5">
                                    @foreach ($feeLines as $line)
                                        <li class="flex items-baseline justify-between gap-3 text-sm">
                                            <span class="text-ink/75">{{ $line->title }}</span>
                                            <span class="shrink-0 font-medium text-ink">{{ $line->formattedAmount() }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 text-right">
                        <form method="POST" action="{{ route('cart.clear') }}" x-data
                              @submit.prevent="if (await window.uprlConfirm({ title: 'Empty your cart?', confirmText: 'Yes, empty it', danger: true })) $el.submit()">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded text-xs text-ink/45 hover:text-crimson focus-ring">Empty cart</button>
                        </form>
                    </div>
                </div>

                {{-- Summary. Sticky on desktop; a normal block on mobile so it never
                     covers the lines on a short screen. --}}
                <aside>
                    <div class="lg:sticky lg:top-24">
                        <div class="rounded-2xl border border-line bg-card p-5 shadow-sm">
                            <h2 class="font-display text-lg font-semibold text-ink">Summary</h2>

                            <dl class="mt-4 space-y-2.5 text-sm">
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="text-ink/60">Subtotal</dt>
                                    <dd class="font-medium text-ink">{{ $totals->formattedSubtotal() }}</dd>
                                </div>
                                @if ($totals->hasDiscount())
                                    <div class="flex items-baseline justify-between gap-3 text-success">
                                        <dt class="flex items-center gap-1.5">
                                            <x-ui.icon name="tag" class="h-4 w-4" />
                                            {{ $totals->coupon->code }}
                                        </dt>
                                        <dd class="font-medium">{{ $totals->formattedDiscount() }}</dd>
                                    </div>
                                @endif
                                <div class="flex items-baseline justify-between gap-3 border-t border-line pt-2.5">
                                    <dt class="font-display text-base font-semibold text-ink">Total</dt>
                                    <dd class="font-display text-xl font-bold text-ink">{{ $totals->formattedTotal() }}</dd>
                                </div>
                            </dl>

                            {{-- Coupon --}}
                            <div class="mt-4 border-t border-line pt-4">
                                @if ($totals->coupon)
                                    <div class="flex items-center justify-between gap-2 rounded-xl bg-success/10 px-3 py-2">
                                        <p class="min-w-0 text-sm">
                                            <span class="font-medium text-success">{{ $totals->coupon->code }}</span>
                                            <span class="block text-xs text-ink/60">{{ $totals->coupon->describe() }}</span>
                                        </p>
                                        <form method="POST" action="{{ route('cart.coupon.remove') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded text-xs text-ink/50 hover:text-crimson focus-ring">Remove</button>
                                        </form>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('cart.coupon.apply') }}" class="space-y-2">
                                        @csrf
                                        <label for="code" class="block text-xs font-medium text-ink/70">Have a discount code?</label>
                                        <div class="flex gap-2">
                                            <x-ui.input id="code" name="code" placeholder="Enter code" class="uppercase"
                                                        :invalid="$errors->has('code')" autocomplete="off" />
                                            <x-ui.button type="submit" variant="secondary">Apply</x-ui.button>
                                        </div>
                                        <x-input-error :messages="$errors->get('code')" />
                                    </form>
                                @endif
                            </div>

                            {{-- Checkout --}}
                            <div class="mt-5">
                                @auth
                                    <x-ui.button class="w-full" :href="route('checkout.show')">
                                        Proceed to checkout
                                    </x-ui.button>
                                @else
                                    {{-- Not a hard bounce: the guest is told what happens next and
                                         their cart survives the login (MergeGuestCart). --}}
                                    <x-ui.button class="w-full" :href="route('login')">
                                        Log in to check out
                                    </x-ui.button>
                                    <p class="mt-2 text-center text-xs text-ink/60">
                                        New here?
                                        <a href="{{ route('register') }}" class="text-crimson hover:underline focus-ring rounded">Create an account</a>.
                                        Your cart will be waiting.
                                    </p>
                                @endauth
                            </div>
                        </div>

                        <p class="mt-3 px-1 text-center text-xs text-ink/45">
                            Prices in {{ Money::currency() }}. You will be able to review everything before paying.
                        </p>
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-public-layout>
