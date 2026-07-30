<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shopping cart, owned either by a signed-in user or by a signed-out visitor
 * identified by a cookie token. Always read and written through CartService — it is
 * the only place a cart's ownership, merging and expiry are decided.
 */
class Cart extends Model
{
    /** @use HasFactory<\Database\Factories\CartFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'session_token', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Courses in the cart, as a keyed collection for cheap membership tests.
     *
     * @return \Illuminate\Support\Collection<int, Course>
     */
    public function courses(): \Illuminate\Support\Collection
    {
        return $this->items->pluck('course')->filter()->keyBy('id');
    }

    public function has(Course $course): bool
    {
        return $this->items->contains('course_id', $course->id);
    }

    /**
     * Sum of the snapshotted item prices. Display only — CheckoutService re-resolves
     * every price from PricingService rather than trusting these.
     */
    public function itemsSubtotal(): float
    {
        return round((float) $this->items->sum('unit_price'), 2);
    }

    public function formattedItemsSubtotal(): string
    {
        return Money::format($this->itemsSubtotal());
    }
}
