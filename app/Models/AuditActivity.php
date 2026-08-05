<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * One entry in the audit trail.
 *
 * Registered as activitylog's model in config/activitylog.php, so EVERY entry —
 * whether written by the LogsAuditActivity trait or explicitly through
 * AuditLogger — is an instance of this class and inherits its guarantees.
 *
 * APPEND-ONLY. An audit trail that can be edited is not evidence of anything, so
 * update and delete are refused at the model level, beneath any controller or
 * policy. There is no UI that attempts either; this is the backstop for code that
 * tries anyway. The single sanctioned exception is retention pruning, which must
 * go through forcePrune() and is bounded by config('audit.retention_days').
 */
class AuditActivity extends SpatieActivity
{
    /** Set only by forcePrune(), for the lifetime of that one call. */
    protected static bool $pruning = false;

    protected static function booted(): void
    {
        static::updating(function (self $activity) {
            // Written once, at creation, and never again.
            if (! static::$pruning) {
                throw new \RuntimeException('The audit log is append-only; entries cannot be modified.');
            }
        });

        static::deleting(function (self $activity) {
            if (! static::$pruning) {
                throw new \RuntimeException('The audit log is append-only; entries cannot be deleted.');
            }
        });
    }

    /**
     * The ONE sanctioned way an entry may leave the table: scheduled retention
     * pruning, older than the configured horizon. Returns the number removed.
     */
    public static function forcePrune(\DateTimeInterface $before): int
    {
        static::$pruning = true;

        try {
            return static::query()->where('created_at', '<', $before)->delete();
        } finally {
            static::$pruning = false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    public function auditEvent(): ?AuditEvent
    {
        return $this->event ? AuditEvent::tryFrom($this->event) : null;
    }

    public function eventLabel(): string
    {
        return $this->auditEvent()?->label()
            ?? ucfirst(str_replace(['_', '.'], ' ', (string) $this->event));
    }

    /**
     * The before side of the diff, or an empty array when this entry is not a
     * change (a login, an export).
     *
     * @return array<string, mixed>
     */
    public function before(): array
    {
        return (array) ($this->properties['old'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return (array) ($this->properties['attributes'] ?? []);
    }

    public function hasDiff(): bool
    {
        return $this->before() !== [] || $this->after() !== [];
    }

    /**
     * Context recorded alongside a diff — the course an item belongs to, the
     * reason for a refund. Everything that is not the diff itself.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        $properties = $this->properties;

        // NOT (array) $properties: activitylog casts this to a Collection, and casting
        // a Collection OBJECT to array yields its mangled protected-property keys
        // ("\0*\0items"), not the data. Element access works either way, which is why
        // before()/after() above are safe — this whole-object conversion is not.
        $properties = $properties instanceof Collection
            ? $properties->toArray()
            : (array) $properties;

        unset($properties['old'], $properties['attributes']);

        return $properties;
    }

    /**
     * Whether a given field's value was withheld. The viewer uses this to render
     * "changed" rather than a misleading empty cell.
     */
    public function isRedacted(string $field): bool
    {
        return ($this->before()[$field] ?? null) === AuditLogger::REDACTED
            || ($this->after()[$field] ?? null) === AuditLogger::REDACTED;
    }

    /**
     * A short human label for whatever this entry was performed on, surviving the
     * subject having since been deleted (the log outlives its subjects).
     */
    public function subjectLabel(): string
    {
        if ($this->subject === null) {
            return $this->subject_type
                ? class_basename($this->subject_type).' #'.$this->subject_id.' (deleted)'
                : '—';
        }

        $subject = $this->subject;

        // `label` is in the chain for PaymentMethod, which has no name/title/code and
        // otherwise rendered as a bare "PaymentMethod #3" in the viewer's subject
        // column — unreadable in exactly the row a security review comes looking for.
        return (string) ($subject->name
            ?? $subject->title
            ?? $subject->label
            ?? $subject->code
            ?? $subject->reference
            ?? class_basename($subject).' #'.$subject->getKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Filtering (the /admin/audit query)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCausedByUser(Builder $query, ?int $userId): void
    {
        $query->when($userId, fn (Builder $q) => $q
            ->where('causer_type', User::class)
            ->where('causer_id', $userId));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfCategory(Builder $query, ?string $category): void
    {
        $query->when($category, fn (Builder $q) => $q->where('log_name', $category));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfEvent(Builder $query, ?string $event): void
    {
        $query->when($event, fn (Builder $q) => $q->where('event', $event));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfSubjectType(Builder $query, ?string $type): void
    {
        $query->when($type, fn (Builder $q) => $q->where('subject_type', $type));
    }

    /**
     * Inclusive on both ends — "from the 1st to the 3rd" must include everything
     * that happened ON the 3rd, not stop at its first second.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }
}
