<?php

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\GradeBand;
use App\Models\GradeScale;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\Question;
use App\Models\Rubric;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Redaction — fields that must NEVER reach a diff
    |--------------------------------------------------------------------------
    |
    | The audit trail exists to show that a sensitive thing changed; it must never
    | become a second copy of the sensitive thing itself. A rotated Paystack secret
    | is the motivating case: the log must record THAT the credentials changed, by
    | whom and when, and nothing whatsoever about their value.
    |
    | Two independent layers, because one is not worth trusting alone:
    |
    |   `models`  an explicit per-model field list. Authoritative.
    |   `keys`    a substring blocklist applied to EVERY property key, whatever
    |             model it came from. Catches a field added to a model later by
    |             someone who never read this file.
    |
    | A redacted value is replaced by App\Support\Audit\AuditLogger::REDACTED, so
    | the viewer can still show "changed" against the field name.
    |
    */

    'redact' => [

        'models' => [
            PaymentMethod::class => ['config'],
            User::class => ['password', 'remember_token', 'two_factor_secret'],
            Order::class => ['gateway_payload'],
        ],

        'keys' => [
            'password',
            'secret',
            'token',
            'api_key',
            'apikey',
            'private_key',
            'credential',
            'signature',
            'webhook_secret',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Noise suppression
    |--------------------------------------------------------------------------
    |
    | Columns whose movement carries no audit meaning. Excluded from every diff so
    | a one-field edit does not render as a wall of unchanged timestamps.
    |
    */

    'ignore' => [
        'updated_at',
        'created_at',
        'remember_token',
        'last_seen_at',
        'current_position_seconds',

        // Section 16. Hiding is already recorded explicitly, with a semantic verb and the
        // course it belongs to; letting the automatic diff log it too would file the same
        // act twice under a vaguer name.
        'hidden_at',

        // The completion snapshot is a large, write-once payload already preserved on the
        // enrollment itself. Copying it into every audit diff would bury the row it sits in.
        'completion_snapshot',
        'completion_snapshot_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models logged automatically
    |--------------------------------------------------------------------------
    |
    | Each of these uses the LogsAuditActivity trait, which records create/update/
    | delete with a redacted diff. Listed here so the hardening report and the
    | coverage test can assert the set, rather than trusting a grep.
    |
    | Events that mean more than "a row changed" — a publish, a reorder, a fee
    | change, a credential rotation — are recorded explicitly by the service or
    | controller that performs them, with an AuditEvent verb.
    |
    */

    'models' => [
        User::class,
        Course::class,
        Module::class,
        Lesson::class,
        Assessment::class,
        Assignment::class,
        Question::class,
        Rubric::class,
        Enrollment::class,
        GradeScale::class,
        GradeBand::class,
        CourseGradeRecord::class,
        Certificate::class,
        Programme::class,
        ProgrammePart::class,
        Coupon::class,
        PaymentMethod::class,
        Order::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | The trail is append-only and has no delete UI. This is the ONLY thing that
    | may remove an entry, and only entries older than the horizon — run from the
    | scheduler (see docs/production.md). Null disables pruning entirely, which is
    | the default: an audit log that quietly forgets is worse than a large one.
    |
    */

    'retention_days' => env('AUDIT_RETENTION_DAYS') ? (int) env('AUDIT_RETENTION_DAYS') : null,

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    | Ceiling on a single CSV export, so one click cannot stream the whole table.
    */

    'export_limit' => 5000,
];
