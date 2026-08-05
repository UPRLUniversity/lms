<?php

namespace App\Support\Audit;

use App\Enums\AuditEvent;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns "a row changed" into "this is what happened".
 *
 * A raw `updated` entry with a diff is technically complete but useless to scan: an
 * administrator looking for who deactivated a discount code, or who last changed what
 * a programme costs, cannot filter on "updated". This resolver inspects what actually
 * MOVED and, where the change has a name, returns it.
 *
 * It runs from LogsAuditActivity::tapActivity, i.e. at the moment the entry is written,
 * which is deliberate: it means EVERY path that causes the transition is covered — the
 * controller, a service, an artisan command, a queued job — rather than only the call
 * sites someone remembered to instrument. And because it renames the single entry
 * rather than adding a second one, the trail never double-reports one change.
 *
 * A model or transition with no entry here simply keeps the generic created/updated/
 * deleted event, which is the correct outcome for changes that carry no special meaning.
 */
class SemanticEventResolver
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function resolve(Model $model, string $eventName, array $before, array $after): ?AuditEvent
    {
        return match (true) {
            $model instanceof Course => $this->course($eventName, $before, $after),
            $model instanceof Coupon => $this->coupon($eventName, $before, $after),
            $model instanceof PaymentMethod => $this->paymentMethod($eventName, $after),
            $model instanceof Programme => $this->programme($eventName, $after),
            $model instanceof Order => $this->order($eventName, $after),
            $model instanceof Enrollment => $this->enrollment($eventName, $after),
            $model instanceof Certificate => $this->certificate($eventName, $after),
            $model instanceof Lesson,
            $model instanceof Assessment,
            $model instanceof Assignment => $this->curriculumItem($eventName, $after),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function course(string $eventName, array $before, array $after): ?AuditEvent
    {
        if ($eventName === 'created') {
            return AuditEvent::CourseCreated;
        }

        if ($eventName === 'deleted') {
            return AuditEvent::CourseDeleted;
        }

        if (! array_key_exists('status', $after)) {
            return $eventName === 'updated' ? AuditEvent::CourseUpdated : null;
        }

        // The workflow transition is the interesting part, not the column write.
        return match ($this->enumValue($after['status'])) {
            CourseStatus::Published->value => AuditEvent::CoursePublished,
            CourseStatus::Review->value => AuditEvent::CourseSubmitted,
            CourseStatus::Archived->value => AuditEvent::CourseArchived,
            CourseStatus::Draft->value => $this->enumValue($before['status'] ?? null) === CourseStatus::Review->value
                ? AuditEvent::CourseReturned      // sent back by a reviewer
                : AuditEvent::CourseRestored,     // brought back from the archive
            default => AuditEvent::CourseUpdated,
        };
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function coupon(string $eventName, array $before, array $after): ?AuditEvent
    {
        if ($eventName === 'created') {
            return AuditEvent::CouponCreated;
        }

        if ($eventName === 'deleted') {
            return AuditEvent::CouponDeleted;
        }

        // Deactivation is the one a finance review comes looking for — whether it
        // happened through the edit form or through the "used, so deactivate rather
        // than delete" path in the controller.
        if (array_key_exists('is_active', $after) && ! $this->truthy($after['is_active'])) {
            return AuditEvent::CouponDeactivated;
        }

        return $eventName === 'updated' ? AuditEvent::CouponUpdated : null;
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function paymentMethod(string $eventName, array $after): ?AuditEvent
    {
        if ($eventName === 'created') {
            return AuditEvent::PaymentMethodCreated;
        }

        if ($eventName === 'deleted') {
            return AuditEvent::PaymentMethodDeleted;
        }

        // `config` holds the gateway credentials. Its VALUE is already redacted by the
        // time anything reads it here; what we record is that a rotation occurred, which
        // is precisely the event a security review is looking for.
        if (array_key_exists('config', $after)) {
            return AuditEvent::PaymentMethodCredentialsRotated;
        }

        if (array_key_exists('is_enabled', $after)) {
            return AuditEvent::PaymentMethodToggled;
        }

        return $eventName === 'updated' ? AuditEvent::PaymentMethodUpdated : null;
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function programme(string $eventName, array $after): ?AuditEvent
    {
        if ($eventName === 'created') {
            return AuditEvent::ProgrammeCreated;
        }

        if ($eventName === 'deleted') {
            return AuditEvent::ProgrammeDeleted;
        }

        // Fees decide what a student is actually charged, so they get their own event
        // rather than hiding inside a general "programme updated".
        $feeFields = ['registration_fee', 'administration_fee', 'per_paper_fee'];

        foreach ($feeFields as $field) {
            if (array_key_exists($field, $after)) {
                return AuditEvent::ProgrammeFeeChanged;
            }
        }

        return $eventName === 'updated' ? AuditEvent::ProgrammeUpdated : null;
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function order(string $eventName, array $after): ?AuditEvent
    {
        if ($eventName !== 'updated') {
            return null;
        }

        if (array_key_exists('status', $after)) {
            return $this->enumValue($after['status']) === 'refunded'
                ? AuditEvent::OrderRefunded
                : AuditEvent::OrderStatusChanged;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function enrollment(string $eventName, array $after): ?AuditEvent
    {
        if ($eventName === 'created') {
            return AuditEvent::EnrollmentCreated;
        }

        if (! array_key_exists('status', $after)) {
            return null;
        }

        return match ($this->enumValue($after['status'])) {
            EnrollmentStatus::Active->value => AuditEvent::EnrollmentApproved,
            EnrollmentStatus::Rejected->value => AuditEvent::EnrollmentRejected,
            EnrollmentStatus::Withdrawn->value => AuditEvent::EnrollmentWithdrawn,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function certificate(string $eventName, array $after): ?AuditEvent
    {
        if ($eventName === 'created') {
            return AuditEvent::CertificateIssued;
        }

        if (array_key_exists('revoked_at', $after)) {
            return $after['revoked_at'] === null
                ? AuditEvent::CertificateRestored
                : AuditEvent::CertificateRevoked;
        }

        return null;
    }

    /**
     * Lessons, assessments and assignments share the two flags that decide what a
     * student must actually do — new in Section 14, and worth their own events because
     * they change the meaning of "complete" for everyone already enrolled.
     *
     * @param  array<string, mixed>  $after
     */
    private function curriculumItem(string $eventName, array $after): ?AuditEvent
    {
        if ($eventName !== 'updated') {
            return null;
        }

        if (array_key_exists('counts_toward_grade', $after)) {
            return AuditEvent::CurriculumGradeWeightChanged;
        }

        if (array_key_exists('is_required', $after)) {
            return AuditEvent::CurriculumRequirementChanged;
        }

        return null;
    }

    /**
     * Attribute values reach here as either a backed enum or its raw scalar,
     * depending on whether the write went through a cast.
     */
    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return $value === null ? null : (string) $value;
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
