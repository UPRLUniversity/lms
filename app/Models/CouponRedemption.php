<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record that a coupon was actually used on a paid order.
 *
 * Written only when an order reaches Paid, never at checkout — a code held on an
 * abandoned or failed order must not burn a redemption. Unique on
 * (coupon_id, order_id), so a replayed gateway webhook cannot count twice.
 */
class CouponRedemption extends Model
{
    /** @use HasFactory<\Database\Factories\CouponRedemptionFactory> */
    use HasFactory;

    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount'];

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
