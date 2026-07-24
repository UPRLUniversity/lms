<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Services\Grades\CourseGradeRecordService;

/**
 * Persists the CourseGradeRecord completion snapshot. Runs synchronously (not queued) —
 * it's a fast, local read-then-write with no external calls, and tests/controllers rely
 * on the snapshot already existing immediately after the request that completed it.
 */
class RecordCourseGradeSnapshot
{
    public function __construct(private readonly CourseGradeRecordService $records) {}

    public function handle(CourseCompleted $event): void
    {
        $this->records->recordCompletion($event->user, $event->course);
    }
}
