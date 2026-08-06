<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * "Hide from students" — the safe alternative to deleting curriculum that already carries
 * student work (Section 16).
 *
 * A hidden item vanishes from the learner's outline and stops counting toward completion
 * and grades, but every attempt, submission, grade and progress row attached to it stays
 * intact. The instructor still sees it in the builder, greyed, with a Restore action.
 *
 * Deliberately NOT SoftDeletes: soft-deleting would take the item out of the builder and
 * out of the gradebook's historical view too, which is the opposite of what's wanted —
 * the record must stay legible to staff.
 *
 * `hidden_at` is intentionally absent from every $fillable: visibility changes go through
 * hide()/restore() so they can be classified and logged, never through mass assignment.
 *
 * Requires: a nullable `hidden_at` timestamp column and a `'hidden_at' => 'datetime'` cast
 * on the using model.
 */
trait Hideable
{
    /**
     * Items students can see. Every learner-facing curriculum read is scoped through this.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeHidden(Builder $query): void
    {
        $query->whereNotNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function isVisible(): bool
    {
        return ! $this->isHidden();
    }

    /**
     * Idempotent — hiding an already-hidden item keeps the original timestamp, so the
     * change log doesn't gain a second "hidden" entry for a repeated click.
     */
    public function hide(): bool
    {
        if ($this->isHidden()) {
            return false;
        }

        $this->forceFill(['hidden_at' => now()])->save();

        return true;
    }

    /**
     * Idempotent, mirroring hide().
     */
    public function restore(): bool
    {
        if (! $this->isHidden()) {
            return false;
        }

        $this->forceFill(['hidden_at' => null])->save();

        return true;
    }
}
