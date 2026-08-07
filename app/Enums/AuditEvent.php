<?php

namespace App\Enums;

/**
 * The verbs the audit trail records.
 *
 * Ordinary model writes (created / updated / deleted) are logged automatically by
 * spatie/laravel-activitylog and keep its own event names. This enum is for the
 * events that MEAN something beyond "a row changed" — the ones an administrator
 * would actually come looking for: who published a course, who moved a lesson, who
 * rotated a gateway key, who changed what a programme costs.
 *
 * The value is stored in activity_log.event; the category becomes log_name, which
 * is what the /admin/audit filter groups by.
 */
enum AuditEvent: string
{
    // Authentication.
    case LoginSucceeded = 'login.succeeded';
    case LoginFailed = 'login.failed';
    case LoggedOut = 'login.logged_out';

    // User lifecycle & access.
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRolesChanged = 'user.roles_changed';
    case UserStatusChanged = 'user.status_changed';
    case InvitationSent = 'user.invitation_sent';

    // Course lifecycle.
    case CourseCreated = 'course.created';
    case CourseUpdated = 'course.updated';
    case CoursePublished = 'course.published';
    case CourseSubmitted = 'course.submitted_for_review';
    case CourseReturned = 'course.returned_to_draft';
    case CourseArchived = 'course.archived';
    case CourseRestored = 'course.restored';
    case CourseDeleted = 'course.deleted';

    // Curriculum (Section 14) — what a student must do, and what counts.
    case CurriculumReordered = 'curriculum.reordered';
    case CurriculumRequirementChanged = 'curriculum.requirement_changed';
    case CurriculumGradeWeightChanged = 'curriculum.counts_toward_grade_changed';

    // Change safety (Section 16) — editing a course students are already on.
    case CurriculumItemHidden = 'curriculum.item_hidden';
    case CurriculumItemRestored = 'curriculum.item_restored';
    case CurriculumDeleteBlocked = 'curriculum.delete_blocked';
    case CourseChangedWithEnrollments = 'course.changed_with_enrollments';

    // Assessment & assignment authoring.
    case AssessmentUpdated = 'assessment.updated';
    case AssessmentPublished = 'assessment.published';
    case AssessmentUnpublished = 'assessment.unpublished';
    case QuestionChanged = 'assessment.question_changed';
    case RubricChanged = 'assessment.rubric_changed';
    case AssignmentUpdated = 'assignment.updated';
    case AssignmentPublished = 'assignment.published';

    // Enrolment decisions.
    case EnrollmentApproved = 'enrollment.approved';
    case EnrollmentRejected = 'enrollment.rejected';
    case EnrollmentCreated = 'enrollment.created';
    case EnrollmentWithdrawn = 'enrollment.withdrawn';

    // Grading.
    case AttemptGraded = 'grading.attempt_graded';
    case SubmissionGraded = 'grading.submission_graded';
    case SubmissionReturned = 'grading.submission_returned';
    case GradebookRecomputed = 'grading.gradebook_recomputed';
    case GradeScaleChanged = 'grading.scale_changed';
    case GradeBandChanged = 'grading.band_changed';
    case DefaultScaleChanged = 'grading.default_scale_changed';

    // Certificates.
    case CertificateIssued = 'certificate.issued';
    case CertificateReissued = 'certificate.reissued';
    case CertificateRevoked = 'certificate.revoked';
    case CertificateRestored = 'certificate.restored';

    // Programmes (Section 11) — fee changes decide what students are charged.
    case ProgrammeCreated = 'programme.created';
    case ProgrammeUpdated = 'programme.updated';
    case ProgrammeFeeChanged = 'programme.fee_changed';
    case ProgrammeDeleted = 'programme.deleted';
    case ProgrammePartChanged = 'programme.part_changed';

    // Commerce (Section 12).
    case CouponCreated = 'commerce.coupon_created';
    case CouponUpdated = 'commerce.coupon_updated';
    case CouponDeactivated = 'commerce.coupon_deactivated';
    case CouponDeleted = 'commerce.coupon_deleted';
    case PaymentMethodCreated = 'commerce.payment_method_created';
    case PaymentMethodUpdated = 'commerce.payment_method_updated';
    case PaymentMethodCredentialsRotated = 'commerce.payment_method_credentials_rotated';
    case PaymentMethodToggled = 'commerce.payment_method_toggled';
    case PaymentMethodDeleted = 'commerce.payment_method_deleted';
    case OrderStatusChanged = 'commerce.order_status_changed';
    case OrderRefunded = 'commerce.order_refunded';

    // This section's own screen.
    case SettingsUpdated = 'settings.updated';

    /**
     * The activity_log.log_name this event files under — the audit viewer's
     * top-level filter.
     */
    public function category(): string
    {
        return str_contains($this->value, '.')
            ? explode('.', $this->value)[0]
            : 'system';
    }

    /**
     * Human-readable verb for the viewer and the CSV export.
     */
    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Signed in',
            self::LoginFailed => 'Failed sign-in',
            self::LoggedOut => 'Signed out',

            self::UserCreated => 'Created an account',
            self::UserUpdated => 'Updated an account',
            self::UserRolesChanged => 'Changed roles',
            self::UserStatusChanged => 'Changed account status',
            self::InvitationSent => 'Sent an invitation',

            self::CourseCreated => 'Created a course',
            self::CourseUpdated => 'Updated a course',
            self::CoursePublished => 'Published a course',
            self::CourseSubmitted => 'Submitted a course for review',
            self::CourseReturned => 'Returned a course to draft',
            self::CourseArchived => 'Archived a course',
            self::CourseRestored => 'Restored a course',
            self::CourseDeleted => 'Deleted a course',

            self::CurriculumReordered => 'Reordered the curriculum',
            self::CurriculumRequirementChanged => 'Changed whether an item is required',
            self::CurriculumGradeWeightChanged => 'Changed whether an item counts toward the grade',
            self::CurriculumItemHidden => 'Hid an item from students',
            self::CurriculumItemRestored => 'Made an item visible again',
            self::CurriculumDeleteBlocked => 'Blocked a delete that held student work',
            self::CourseChangedWithEnrollments => 'Changed a course students are taking',

            self::AssessmentUpdated => 'Updated an assessment',
            self::AssessmentPublished => 'Published an assessment',
            self::AssessmentUnpublished => 'Unpublished an assessment',
            self::QuestionChanged => 'Changed a question',
            self::RubricChanged => 'Changed a rubric',
            self::AssignmentUpdated => 'Updated an assignment',
            self::AssignmentPublished => 'Published an assignment',

            self::EnrollmentApproved => 'Approved an enrolment',
            self::EnrollmentRejected => 'Rejected an enrolment',
            self::EnrollmentCreated => 'Enrolled a learner',
            self::EnrollmentWithdrawn => 'Withdrew an enrolment',

            self::AttemptGraded => 'Graded an attempt',
            self::SubmissionGraded => 'Graded a submission',
            self::SubmissionReturned => 'Returned a submission',
            self::GradebookRecomputed => 'Recomputed a grade record',
            self::GradeScaleChanged => 'Changed a grade scale',
            self::GradeBandChanged => 'Changed a grade band',
            self::DefaultScaleChanged => 'Changed the default grade scale',

            self::CertificateIssued => 'Issued a certificate',
            self::CertificateReissued => 'Re-issued a certificate',
            self::CertificateRevoked => 'Revoked a certificate',
            self::CertificateRestored => 'Restored a certificate',

            self::ProgrammeCreated => 'Created a programme',
            self::ProgrammeUpdated => 'Updated a programme',
            self::ProgrammeFeeChanged => 'Changed programme fees',
            self::ProgrammeDeleted => 'Deleted a programme',
            self::ProgrammePartChanged => 'Changed a programme part',

            self::CouponCreated => 'Created a discount code',
            self::CouponUpdated => 'Updated a discount code',
            self::CouponDeactivated => 'Deactivated a discount code',
            self::CouponDeleted => 'Deleted a discount code',
            self::PaymentMethodCreated => 'Added a payment method',
            self::PaymentMethodUpdated => 'Updated a payment method',
            self::PaymentMethodCredentialsRotated => 'Rotated gateway credentials',
            self::PaymentMethodToggled => 'Enabled/disabled a payment method',
            self::PaymentMethodDeleted => 'Removed a payment method',
            self::OrderStatusChanged => 'Changed an order\'s status',
            self::OrderRefunded => 'Recorded a refund',

            self::SettingsUpdated => 'Changed system settings',
        };
    }

    /**
     * Events that describe something being taken away, destroyed or refused —
     * tinted differently in the viewer so a scan finds them first.
     */
    public function isDestructive(): bool
    {
        return in_array($this, [
            self::LoginFailed,
            self::UserStatusChanged,
            self::CourseArchived,
            self::CourseDeleted,
            self::EnrollmentRejected,
            self::EnrollmentWithdrawn,
            self::CertificateRevoked,
            self::ProgrammeDeleted,
            self::CouponDeactivated,
            self::CouponDeleted,
            self::PaymentMethodDeleted,
            self::PaymentMethodCredentialsRotated,
            self::OrderRefunded,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return array_values(array_unique(
            array_map(fn (self $e) => $e->category(), self::cases()),
        ));
    }

    public static function tryLabel(?string $value): ?string
    {
        return $value ? (self::tryFrom($value)?->label() ?? $value) : null;
    }
}
