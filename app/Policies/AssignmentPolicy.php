<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\User;

/**
 * Authorization for assignments. Authoring follows the course's management rule (mirrors
 * AssessmentPolicy); submitting is for enrolled students of a published assignment;
 * grading additionally needs assignments.grade. The auditor sees everything read-only
 * through view()/reviewQueue() via its assignments.view permission.
 */
class AssignmentPolicy
{
    public function __construct(private readonly CoursePolicy $courses) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value) || $user->hasRole(Role::Auditor->value);
    }

    /**
     * View in a management context (builder, submissions) — manager or auditor.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        if ($this->manage($user, $assignment)) {
            return true;
        }

        return $user->hasRole(Role::Auditor->value) && $user->can(Permission::AssignmentsView->value);
    }

    /**
     * Author/edit the assignment — the course's management rule.
     */
    public function manage(User $user, Assignment $assignment): bool
    {
        return $this->courses->update($user, $assignment->course);
    }

    /**
     * Open the student assignment page / hand work in: a published assignment, and an
     * actively-enrolled student of its course.
     */
    public function submit(User $user, Assignment $assignment): bool
    {
        if (! $assignment->isPublished()) {
            return false;
        }

        $enrollment = $assignment->course->enrollmentFor($user);

        return $enrollment !== null && $enrollment->grantsLearningAccess();
    }

    /**
     * Grade submissions on this assignment — a manager who also holds assignments.grade.
     */
    public function grade(User $user, Assignment $assignment): bool
    {
        return $user->can(Permission::AssignmentsGrade->value) && $this->manage($user, $assignment);
    }
}
