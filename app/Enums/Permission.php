<?php

namespace App\Enums;

/**
 * Granular permissions. Defined now — even though only a few are checked in
 * Section 1 — so the matrix grows by addition, not by retro-fitting. Read-style
 * permissions end in ".view" and are the only ones the auditor receives.
 */
enum Permission: string
{
    // User & access administration.
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';

    // Courses & content.
    case CoursesView = 'courses.view';
    case CoursesCreate = 'courses.create';
    case CoursesManage = 'courses.manage';

    // Enrolment.
    case EnrollmentsView = 'enrollments.view';
    case EnrollmentsApprove = 'enrollments.approve';

    // Assessment.
    case AssessmentsView = 'assessments.view';
    case AssessmentsGrade = 'assessments.grade';

    // Reporting.
    case ReportsView = 'reports.view';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * The read-only subset granted to the auditor.
     *
     * @return array<int, string>
     */
    public static function readOnly(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $value) => str_ends_with($value, '.view'),
        ));
    }
}
