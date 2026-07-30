<x-app-layout :title="$coupon->code">
    <div class="mx-auto max-w-2xl space-y-6">
        <a href="{{ route('admin.coupons.index') }}" class="inline-flex items-center gap-1.5 text-sm text-ink/60 hover:text-ink focus-ring rounded">
            <x-ui.icon name="arrow-left" class="h-4 w-4" /> Discount codes
        </a>

        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-2xl font-semibold text-ink">{{ $coupon->code }}</h2>
            <x-ui.badge variant="crimson">{{ $coupon->describe() }}</x-ui.badge>
            <span class="text-sm text-ink/55">
                {{ $coupon->redemptionCount() }} used @if ($coupon->max_redemptions) of {{ $coupon->max_redemptions }}@endif
            </span>
        </div>

        <x-ui.card>
            <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}" class="space-y-6">
                @csrf
                @method('PUT')
                @include('admin.coupons._form', ['coupon' => $coupon])

                <div class="flex justify-end gap-3 border-t border-line pt-5">
                    <x-ui.button variant="ghost" :href="route('admin.coupons.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Save changes</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
