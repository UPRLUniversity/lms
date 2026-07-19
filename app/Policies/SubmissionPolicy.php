<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Who may see and act on a submission version. The owning student always reads their
 * own history (any version); course managers grade; the auditor reads everything.
 */
class SubmissionPolicy
{
    /**
     * Read one submission version (the version viewer + grading workspace).
     */
    public function view(User $user, Submission $submission): bool
    {
        if ($submission->user_id === $user->id) {
            return true;
        }

        if (Gate::forUser($user)->allows('manage', $submission->assignment)) {
            return true;
        }

        return $user->hasRole(Role::Auditor->value) && $user->can(Permission::AssignmentsView->value);
    }

    /**
     * Grade this submission / return it for resubmission.
     */
    public function grade(User $user, Submission $submission): bool
    {
        return Gate::forUser($user)->allows('grade', $submission->assignment);
    }
}
