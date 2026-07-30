@php
    use App\Enums\CouponScope;
@endphp

<x-app-layout title="Discount codes">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Discount codes</h2>
                <p class="mt-1 max-w-2xl text-ink/70">
                    @if ($isAdmin)
                        Codes students can apply at checkout. Percentage, fixed amount, or free.
                    @else
                        Codes for the courses you teach. Percentage, fixed amount, or free.
                    @endif
                </p>
            </div>
            <x-ui.button :href="route('admin.coupons.create')">
                <x-ui.icon name="plus" class="h-5 w-5" /> New code
            </x-ui.button>
        </div>

        @if ($coupons->isEmpty())
            <x-ui.empty-state
                icon="tag"
                title="No codes yet"
                description="Create a code to run a promotion, offer a scholarship place, or give a partner organisation a discount.">
                <x-slot name="action">
                    <x-ui.button :href="route('admin.coupons.create')">Create a code</x-ui.button>
                </x-slot>
            </x-ui.empty-state>
        @else
            <div class="space-y-3">
                @foreach ($coupons as $coupon)
                    @php
                        $exhausted = $coupon->isExhausted();
                        $expired = $coupon->hasExpired();
                        $live = $coupon->is_active && ! $expired && ! $exhausted && $coupon->hasStarted();
                    @endphp
                    <x-ui.card>
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-lg bg-ink/5 px-2.5 py-1 font-mono text-sm font-bold tracking-wide text-ink">
                                        {{ $coupon->code }}
                                    </span>
                                    <x-ui.badge variant="crimson">{{ $coupon->describe() }}</x-ui.badge>

                                    @if (! $live)
                                        <x-ui.badge>
                                            {{ $exhausted ? 'Used up' : ($expired ? 'Expired' : (! $coupon->hasStarted() ? 'Scheduled' : 'Inactive')) }}
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge variant="success">Live</x-ui.badge>
                                    @endif
                                </div>

                                @if ($coupon->name)
                                    <p class="mt-1.5 text-sm text-ink/75">{{ $coupon->name }}</p>
                                @endif

                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink/55">
                                    <span>
                                        @switch($coupon->scope)
                                            @case(CouponScope::Course)
                                                {{ $coupon->course?->code ?? 'Deleted course' }}
                                                @break
                                            @case(CouponScope::Programme)
                                                {{ $coupon->programme?->code ?? 'Deleted programme' }} programme
                                                @break
                                            @default
                                                Any course
                                        @endswitch
                                    </span>
                                    <span aria-hidden="true">·</span>
                                    <span>
                                        {{ $coupon->redemptions_count }} used @if ($coupon->max_redemptions) of {{ $coupon->max_redemptions }}@endif
                                    </span>
                                    @if ($coupon->expires_at)
                                        <span aria-hidden="true">·</span>
                                        <span>{{ $expired ? 'Expired' : 'Ends' }} {{ $coupon->expires_at->isoFormat('D MMM YYYY') }}</span>
                                    @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.button size="sm" variant="ghost" :href="route('admin.coupons.edit', $coupon)">
                                    <x-ui.icon name="pencil" class="h-4 w-4" /> Edit
                                </x-ui.button>
                                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}"
                                      onsubmit="event.preventDefault(); window.uprlConfirm({ title: 'Delete {{ $coupon->code }}?', text: 'Codes that have already been used are deactivated instead.', confirmText: 'Delete', danger: true }).then(ok => ok &amp;&amp; this.submit());">
                                    @csrf @method('DELETE')
                                    <x-ui.button size="sm" variant="ghost" type="submit" class="text-crimson">Delete</x-ui.button>
                                </form>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{ $coupons->links() }}
        @endif
    </div>
</x-app-layout>
