<?php

namespace App\Events;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired exactly once per genuine completion — the moment `LearningService::recalculate()`
 * flips an enrollment from not-Completed to Completed (never on a no-op recalculation
 * while already complete). This is the shared pipeline Section 6.5's grade snapshot
 * listens on, and the one Section 7's certificate issuance will hook the same way.
 */
class CourseCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly Course $course,
        public readonly Enrollment $enrollment,
    ) {}
}
