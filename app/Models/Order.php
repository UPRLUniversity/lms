<?php

namespace App\Models;

use App\Enums\OrderItemKind;
use App\Enums\OrderStatus;
use App\Models\Concerns\LogsAuditActivity;
use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase. Written by CheckoutService, advanced to Paid only by
 * OrderFulfilmentService, and never edited afterwards — an order is the record of
 * what a student was charged, so a later price change must not rewrite it.
 *
 * Addressed publicly by its ULID `reference`, never its primary key.
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'reference',
        'user_id',
        'status',
        'subtotal',
        'discount_total',
        'total',
        'currency',
        'coupon_id',
        'coupon_code',
        'payment_method_key',
        'gateway_reference',
        'gateway_payload',
        'billing',
        'paid_at',
        'cancelled_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'gateway_payload' => 'array',
            'billing' => 'array',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Order>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', OrderStatus::Paid->value);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * Course lines only, excluding the programme entry fees.
     *
     * @return Collection<int, OrderItem>
     */
    public function courseItems(): Collection
    {
        return $this->items->where('kind', OrderItemKind::Course)->values();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function feeItems(): Collection
    {
        return $this->items->filter(fn (OrderItem $i) => $i->kind->isEntryFee())->values();
    }

    /**
     * Programmes this order paid an entry fee for — read by PricingService so a
     * student is never charged registration twice for the same programme.
     *
     * @return array<int, int>
     */
    public function entryFeeProgrammeIds(): array
    {
        return $this->feeItems()->pluck('programme_id')->filter()->unique()->values()->all();
    }

    public function formattedTotal(): string
    {
        return Money::format($this->total);
    }

    /**
     * A short display form of the reference. The full ULID is correct for gateways
     * and support, but unreadable in a table.
     */
    public function shortReference(): string
    {
        return strtoupper(substr((string) $this->reference, -8));
    }
}
