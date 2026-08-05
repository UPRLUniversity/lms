<?php

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code. All validation and application goes through CouponService — this
 * model answers questions about itself but never decides whether a given cart may
 * use it.
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'scope',
        'course_id',
        'programme_id',
        'max_redemptions',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'scope' => CouponScope::class,
            'value' => 'decimal:2',
            'max_redemptions' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Programme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * @param  Builder<Coupon>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $now = now();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function redemptionCount(): int
    {
        return $this->relationLoaded('redemptions')
            ? $this->redemptions->count()
            : $this->redemptions()->count();
    }

    public function isExhausted(): bool
    {
        return $this->max_redemptions !== null && $this->redemptionCount() >= $this->max_redemptions;
    }

    public function redemptionsBy(User $user): int
    {
        return $this->redemptions()->where('user_id', $user->id)->count();
    }

    public function describe(): string
    {
        return $this->type->describe((float) $this->value);
    }

    /**
     * Whether this coupon may discount a given course, by scope alone — quantity,
     * window and per-user limits are CouponService's business.
     */
    public function coversCourse(Course $course): bool
    {
        return match ($this->scope) {
            CouponScope::Global => true,
            CouponScope::Course => $this->course_id === $course->id,
            CouponScope::Programme => $course->programmeParts
                ->contains(fn ($part) => $part->programme_id === $this->programme_id),
        };
    }
}
