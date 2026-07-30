<?php

namespace App\Models;

use App\Enums\OrderItemKind;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an order. `title` and the money columns are SNAPSHOTS taken at
 * checkout — the course/programme links exist for reporting, but a rename or
 * reprice must never change what a past order says was bought and charged.
 */
class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'kind',
        'course_id',
        'programme_id',
        'title',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'kind' => OrderItemKind::class,
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Nullable: the course may since have been deleted, and the line must survive it.
     *
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

    public function formattedLineTotal(): string
    {
        return Money::format($this->line_total);
    }
}
