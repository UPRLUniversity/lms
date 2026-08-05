<?php

namespace App\Support\Audit;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;

/**
 * The one way anything writes to the audit trail.
 *
 * Everything funnels through here rather than calling activity() directly, for two
 * reasons that matter:
 *
 *   1. REDACTION IS NOT OPTIONAL. Every property, from every caller, passes through
 *      the redactor before it is persisted. A gateway secret cannot leak into the
 *      log by someone forgetting — there is no code path that skips it.
 *   2. Events carry a typed verb (AuditEvent) and a consistent category, so the
 *      viewer can filter and label them without string-matching descriptions.
 *
 * Entries are append-only: nothing in the app updates or deletes an Activity, the
 * viewer offers no delete, and ActivityObserver blocks both at the model level.
 */
class AuditLogger
{
    /** Stands in for any value that must never be persisted. */
    public const REDACTED = '••• redacted •••';

    /**
     * Record an event.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AuditEvent $event,
        ?Model $subject = null,
        array $properties = [],
        ?string $description = null,
    ): ?Activity {
        $logger = activity($event->category())
            ->event($event->value)
            ->withProperties($this->redact($properties, $subject));

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer = auth()->user()) {
            $logger->causedBy($causer);
        }

        return $logger->log($description ?? $event->label());
    }

    /**
     * Record an event as a before/after diff.
     *
     * Only keys that actually MOVED are kept, and both sides are trimmed to the
     * same key set — so the viewer can render a clean two-column comparison and a
     * one-field edit does not look like a rewrite.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $extra  merged alongside the diff (context such
     *                                       as a course id) — also redacted
     */
    public function recordChange(
        AuditEvent $event,
        ?Model $subject,
        array $old,
        array $new,
        array $extra = [],
        ?string $description = null,
    ): ?Activity {
        [$oldDiff, $newDiff] = $this->diff($old, $new);

        if ($oldDiff === [] && $newDiff === [] && $extra === []) {
            return null;   // nothing moved — do not write an empty entry
        }

        return $this->record(
            $event,
            $subject,
            // spatie's own convention for a diff, so the viewer renders entries
            // from the automatic model logging and from explicit calls identically.
            ['old' => $oldDiff, 'attributes' => $newDiff] + $extra,
            $description,
        );
    }

    /**
     * Reduce two attribute sets to just what differs between them.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function diff(array $old, array $new): array
    {
        $ignored = config('audit.ignore', []);
        $keys = array_diff(
            array_unique([...array_keys($old), ...array_keys($new)]),
            $ignored,
        );

        $oldDiff = [];
        $newDiff = [];

        foreach ($keys as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($this->unchanged($before, $after)) {
                continue;
            }

            $oldDiff[$key] = $before;
            $newDiff[$key] = $after;
        }

        return [$oldDiff, $newDiff];
    }

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    */

    /**
     * Replace every sensitive value with the REDACTED marker, recursively.
     *
     * Recursion matters: a diff arrives as ['old' => [...], 'attributes' => [...]],
     * so a `config` key holding gateway credentials is always at least one level
     * down and a shallow pass would sail straight past it.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function redact(array $properties, ?Model $subject = null): array
    {
        $fields = $subject !== null
            ? (config('audit.redact.models.'.$subject::class, []))
            : [];

        return $this->redactArray($properties, $fields);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $modelFields
     * @return array<string, mixed>
     */
    private function redactArray(array $values, array $modelFields): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitive($key, $modelFields)) {
                $result[$key] = self::REDACTED;

                continue;
            }

            $result[$key] = is_array($value)
                ? $this->redactArray($value, $modelFields)
                : $value;
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $modelFields
     */
    private function isSensitive(string $key, array $modelFields): bool
    {
        if (in_array($key, $modelFields, true)) {
            return true;
        }

        $needle = strtolower($key);

        foreach (config('audit.redact.keys', []) as $blocked) {
            if (str_contains($needle, strtolower($blocked))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comparison that treats the values a form and a database round-trip produce
     * for the same thing as equal — "1" vs true, "12.00" vs 12.0, null vs "" —
     * so a save that changed nothing does not manufacture a diff.
     */
    private function unchanged(mixed $before, mixed $after): bool
    {
        if ($before === $after) {
            return true;
        }

        if ($before === null || $after === null) {
            // null and empty-string are the same "not set" for audit purposes,
            // but null and 0 are genuinely different and must survive.
            return ($before ?? '') === '' && ($after ?? '') === '';
        }

        if (is_bool($before) || is_bool($after)) {
            return filter_var($before, FILTER_VALIDATE_BOOLEAN) === filter_var($after, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_numeric($before) && is_numeric($after)) {
            return (float) $before === (float) $after;
        }

        if (is_array($before) && is_array($after)) {
            return $before == $after;
        }

        if ((is_scalar($before) || $before instanceof \Stringable)
            && (is_scalar($after) || $after instanceof \Stringable)) {
            return (string) $before === (string) $after;
        }

        return false;
    }
}
