<?php

namespace App\Policies;

use App\Models\GeneratedReport;
use App\Models\User;

/**
 * A generated report file is sensitive (it aggregates other people's grades and
 * enrolment). Only the person who requested it may download it — the super-admin also
 * passes via the Gate::before bypass. Auto-discovered for the GeneratedReport model.
 */
class GeneratedReportPolicy
{
    public function download(User $user, GeneratedReport $report): bool
    {
        return $report->user_id === $user->id;
    }
}
