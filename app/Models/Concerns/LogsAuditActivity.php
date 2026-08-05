<?php

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Support\Audit\AuditLogger;
use App\Support\Audit\SemanticEventResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Automatic create/update/delete logging for a model, with this application's
 * rules applied — every model in config('audit.models') uses it.
 *
 * What it adds on top of spatie's LogsActivity:
 *
 *   - REDACTION. tapActivity() runs every recorded property through AuditLogger
 *     before it is persisted, so a model gaining a secret column later is covered
 *     without anyone remembering to come back here. This is the reason the trait
 *     exists rather than each model configuring LogOptions itself.
 *   - A stable log_name per model, so /admin/audit can group "everything about
 *     the store" or "everything about a course" without knowing class names.
 *   - Noise suppression: timestamps and other uninteresting columns never appear.
 *
 * A model may still override auditLogName() or auditIgnored() for its own needs.
 */
trait LogsAuditActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->auditLogName())
            ->logOnly($this->auditLoggedAttributes())
            ->dontLogIfAttributesChangedOnly($this->auditIgnored())
            // An update that moved nothing writes nothing.
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Which attributes to track. Defaults to everything fillable, which is the
     * right unit of "what a human can change", minus the ignored columns.
     *
     * @return array<int, string>
     */
    protected function auditLoggedAttributes(): array
    {
        $fillable = $this->getFillable();

        return $fillable === []
            ? ['*']
            : array_values(array_diff($fillable, $this->auditIgnored()));
    }

    /**
     * @return array<int, string>
     */
    protected function auditIgnored(): array
    {
        return config('audit.ignore', []);
    }

    /**
     * The activity_log.log_name — the viewer's category filter. Derived from the
     * class name (Course → course, PaymentMethod → payment-method) so a new
     * audited model needs no registration.
     */
    protected function auditLogName(): string
    {
        return Str::kebab(class_basename($this));
    }

    /**
     * Last stop before an entry is written. Two things happen here, and both happen
     * here specifically so that no code path can skip them:
     *
     *   REDACTION — every property is filtered before it is persisted, so a model
     *   gaining a secret column later is covered without anyone remembering to.
     *
     *   NAMING — a change with a real-world meaning (a publish, a deactivation, a
     *   credential rotation, a fee change) is relabelled from the generic "updated"
     *   to that meaning, so the audit viewer can filter on it. The entry is RENAMED,
     *   never duplicated: one change stays one row.
     */
    /** The only event names Eloquent itself produces. Anything else was authored. */
    private const MODEL_EVENTS = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $properties = $activity->properties;

        $raw = $properties instanceof Collection
            ? $properties->toArray()
            : (array) $properties;

        // REDACTION IS UNCONDITIONAL. spatie taps the subject for manually-logged
        // entries too (ActivityLogger::log), which is exactly why this must run for
        // every entry regardless of where it came from.
        $activity->properties = collect(app(AuditLogger::class)->redact($raw, $this));

        // NAMING IS NOT. An entry AuditLogger wrote on purpose already carries the
        // right event and a description its author chose; overwriting them here would
        // turn "Signed in" into "User X was updated" and silently discard the verb the
        // audit viewer filters on.
        if (! in_array($eventName, self::MODEL_EVENTS, true)) {
            return;
        }

        // Resolve against the RAW changes, not the redacted copy: the resolver asks
        // which fields moved, never what they moved to, so redaction cannot blind it.
        $semantic = app(SemanticEventResolver::class)->resolve(
            $this,
            $eventName,
            (array) ($raw['old'] ?? []),
            (array) ($raw['attributes'] ?? []),
        );

        if ($semantic !== null) {
            $activity->event = $semantic->value;
            $activity->log_name = $semantic->category();
            $activity->description = $this->auditSemanticDescription($semantic);

            return;
        }

        // A readable one-liner for the list view; the diff carries the detail.
        $activity->description = $this->auditDescription($eventName);
    }

    protected function auditSemanticDescription(AuditEvent $event): string
    {
        return strtolower($event->label()).' — '.$this->auditSubjectLabel();
    }

    /**
     * The best human name this record has. `label` is in the chain for PaymentMethod,
     * which has no name/title/code and would otherwise read as a bare id in the viewer.
     */
    protected function auditSubjectLabel(): string
    {
        if (method_exists($this, 'auditLabel')) {
            return (string) $this->auditLabel();
        }

        return (string) ($this->name
            ?? $this->title
            ?? $this->label
            ?? $this->code
            ?? $this->reference
            ?? "#{$this->getKey()}");
    }

    protected function auditDescription(string $eventName): string
    {
        $label = $this->auditSubjectLabel();
        $noun = Str::headline(class_basename($this));

        return match ($eventName) {
            'created' => "{$noun} “{$label}” was created",
            'updated' => "{$noun} “{$label}” was updated",
            'deleted' => "{$noun} “{$label}” was deleted",
            'restored' => "{$noun} “{$label}” was restored",
            default => "{$noun} “{$label}” — {$eventName}",
        };
    }
}
