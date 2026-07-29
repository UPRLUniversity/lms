<?php

namespace App\Enums;

/**
 * Every kind of notification the platform sends, backing the `type` a stored
 * database notification maps to (via each Notification class's own `type()`) and
 * the key used inside `User.learning_preferences['notifications']` for per-type
 * channel toggles. Adding a new notification means adding a case here first.
 */
enum NotificationType: string
{
    // Student.
    case EnrollmentConfirmed = 'enrollment_confirmed';
    case EnrollmentApproved = 'enrollment_approved';
    case EnrollmentRejected = 'enrollment_rejected';
    case WaitlistPromoted = 'waitlist_promoted';
    case AssignmentGraded = 'assignment_graded';
    case AssignmentReturned = 'assignment_returned';
    case AttemptGraded = 'attempt_graded';
    case CertificateIssued = 'certificate_issued';
    case AssignmentDueSoon = 'assignment_due_soon';
    case CourseAnnouncement = 'course_announcement';

    // Communication (Section 9) — reaches students and staff alike.
    case NewMessage = 'new_message';
    case ForumReply = 'forum_reply';

    // Instructor.
    case CourseApproved = 'course_approved';
    case CourseReturned = 'course_returned';
    case NewSubmission = 'new_submission';
    case NewPendingEnrollment = 'new_pending_enrollment';

    // Admin.
    case CourseSubmittedForReview = 'course_submitted_for_review';
    case BulkImportCompleted = 'bulk_import_completed';
    case ReportReady = 'report_ready';

    public function label(): string
    {
        return match ($this) {
            self::EnrollmentConfirmed => 'Enrollment confirmed',
            self::EnrollmentApproved => 'Enrollment approved',
            self::EnrollmentRejected => 'Enrollment declined',
            self::WaitlistPromoted => 'Waitlist promotion',
            self::AssignmentGraded => 'Assignment graded',
            self::AssignmentReturned => 'Assignment returned for resubmission',
            self::AttemptGraded => 'Exam/essay graded',
            self::CertificateIssued => 'Certificate issued',
            self::AssignmentDueSoon => 'Assignment due soon',
            self::CourseAnnouncement => 'Course announcements',
            self::NewMessage => 'New messages',
            self::ForumReply => 'Forum replies',
            self::CourseApproved => 'Course approved',
            self::CourseReturned => 'Course returned with note',
            self::NewSubmission => 'New submission to grade',
            self::NewPendingEnrollment => 'New pending enrollment',
            self::CourseSubmittedForReview => 'Course submitted for review',
            self::BulkImportCompleted => 'Bulk import completed',
            self::ReportReady => 'Report ready to download',
        };
    }

    /**
     * Resolved by <x-ui.icon>.
     */
    public function icon(): string
    {
        return match ($this) {
            self::EnrollmentConfirmed, self::EnrollmentApproved, self::WaitlistPromoted => 'check-circle',
            self::EnrollmentRejected => 'x',
            self::AssignmentGraded, self::AttemptGraded => 'clipboard-check',
            self::AssignmentReturned => 'arrow-path',
            self::CertificateIssued => 'certificate',
            self::AssignmentDueSoon => 'clock',
            self::CourseAnnouncement => 'megaphone',
            self::NewMessage => 'chat',
            self::ForumReply => 'chat',
            self::CourseApproved => 'check',
            self::CourseReturned => 'inbox',
            self::NewSubmission => 'document-text',
            self::NewPendingEnrollment => 'user-plus',
            self::CourseSubmittedForReview => 'flag',
            self::BulkImportCompleted => 'users',
            self::ReportReady => 'download',
        };
    }

    /**
     * Semantic colour tone for the notification's icon tile — success (green) for
     * good news, crimson for something needing attention or a knock-back, gold for
     * achievements and workflow prompts. Maps to brand tokens in the views.
     *
     * @return 'success'|'crimson'|'gold'
     */
    public function tone(): string
    {
        return match ($this) {
            self::EnrollmentConfirmed, self::EnrollmentApproved, self::WaitlistPromoted, self::CourseApproved => 'success',
            self::EnrollmentRejected, self::AssignmentReturned, self::CourseReturned => 'crimson',
            self::CertificateIssued, self::AssignmentGraded, self::AttemptGraded, self::AssignmentDueSoon,
            self::CourseAnnouncement, self::NewMessage, self::ForumReply, self::NewSubmission,
            self::NewPendingEnrollment, self::CourseSubmittedForReview, self::BulkImportCompleted,
            self::ReportReady => 'gold',
        };
    }

    /**
     * Groups the preferences matrix on /profile into readable sections.
     */
    public function category(): string
    {
        return match ($this) {
            self::EnrollmentConfirmed, self::EnrollmentApproved, self::EnrollmentRejected, self::WaitlistPromoted => 'Enrollment',
            self::AssignmentGraded, self::AssignmentReturned, self::AttemptGraded, self::CertificateIssued => 'Grades & certificates',
            self::AssignmentDueSoon => 'Reminders',
            self::CourseAnnouncement => 'Course announcements',
            self::NewMessage, self::ForumReply => 'Messages & forums',
            self::CourseApproved, self::CourseReturned, self::CourseSubmittedForReview => 'Course workflow',
            self::NewSubmission, self::NewPendingEnrollment => 'Teaching',
            self::BulkImportCompleted, self::ReportReady => 'Administration',
        };
    }

    /**
     * Status-changing / can't-miss types: in-app is always on regardless of the
     * user's saved preference (the toggle for these renders disabled+checked).
     */
    public function isCritical(): bool
    {
        return match ($this) {
            self::EnrollmentApproved, self::EnrollmentRejected, self::WaitlistPromoted, self::CertificateIssued => true,
            default => false,
        };
    }

    /**
     * Eligible to be withheld from immediate e-mail and folded into the student's
     * daily digest instead, when they've opted into learning_preferences.email_digest.
     * Time-sensitive / status-changing types are never digested.
     */
    public function isDigestible(): bool
    {
        return match ($this) {
            self::AssignmentGraded, self::AttemptGraded, self::CertificateIssued, self::CourseAnnouncement, self::AssignmentReturned => true,
            default => false,
        };
    }

    /**
     * Tailwind classes for a tinted icon tile of the given tone. One source of truth
     * so the bell dropdown (JSON) and the server-rendered pages tint identically.
     * Gold text uses the darker `gold-ink` token to hold WCAG AA on a light tile.
     */
    public static function toneClasses(string $tone): string
    {
        return match ($tone) {
            'success' => 'bg-success/10 text-success',
            'crimson' => 'bg-crimson/10 text-crimson',
            default => 'bg-gold/15 text-gold-ink',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
