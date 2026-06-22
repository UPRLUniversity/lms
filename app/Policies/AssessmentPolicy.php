<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\User;

/**
 * Authorization for assessments. Authoring follows the course's management rule (mirrors
 * CoursePolicy::manageCurriculum); taking is for enrolled students of a published
 * assessment; grading additionally needs the assessments.grade permission.
 */
class AssessmentPolicy
{
    public function __construct(private readonly CoursePolicy $courses) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value) || $user->hasRole(Role::Auditor->value);
    }

    /**
     * View the assessment in a management context (builder / results) — manager or auditor.
     */
    public function view(User $user, Assessment $assessment): bool
    {
        if ($this->manage($user, $assessment)) {
            return true;
        }

        return $user->hasRole(Role::Auditor->value) && $user->can(Permission::AssessmentsView->value);
    }

    /**
     * Author/edit the assessment — the course's management rule.
     */
    public function manage(User $user, Assessment $assessment): bool
    {
        return $this->courses->update($user, $assessment->course);
    }

    /**
     * Preview-as-student in the builder — same as managing it.
     */
    public function preview(User $user, Assessment $assessment): bool
    {
        return $this->manage($user, $assessment);
    }

    /**
     * Begin/continue an attempt: a published assessment, and an actively-enrolled student of
     * its course. Staff preview through preview(), not take().
     */
    public function take(User $user, Assessment $assessment): bool
    {
        if (! $assessment->isPublished()) {
            return false;
        }

        $enrollment = $assessment->course->enrollmentFor($user);

        return $enrollment !== null && $enrollment->grantsLearningAccess();
    }

    /**
     * Grade attempts on this assessment — a manager who also holds assessments.grade.
     */
    public function grade(User $user, Assessment $assessment): bool
    {
        return $user->can(Permission::AssessmentsGrade->value) && $this->manage($user, $assessment);
    }
}
