<?php

namespace App\Policies;

use App\Models\Attempt;
use App\Models\User;

/**
 * Authorization for a single attempt. The student owns their own attempt (view + continue);
 * an instructor who can grade the assessment may view and grade any attempt on it.
 */
class AttemptPolicy
{
    public function __construct(private readonly AssessmentPolicy $assessments) {}

    public function view(User $user, Attempt $attempt): bool
    {
        return $this->owns($user, $attempt) || $this->assessments->grade($user, $attempt->assessment);
    }

    /**
     * Continue / autosave / submit — only the owning student, only while in progress.
     */
    public function continue(User $user, Attempt $attempt): bool
    {
        return $this->owns($user, $attempt) && $attempt->isInProgress();
    }

    public function grade(User $user, Attempt $attempt): bool
    {
        return $this->assessments->grade($user, $attempt->assessment);
    }

    private function owns(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }
}
