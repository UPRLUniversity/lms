<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Notification schedule (Section 8)
|--------------------------------------------------------------------------
| Laravel 12's bootstrap-style scheduling — no Console/Kernel.php. Hourly for
| due-soon (the 48h window is checked precisely against `due_at` each run, and
| assignment_due_reminders makes re-running any hour harmless); every 15 minutes
| for the pending-enrolment digest per the brief; once daily for the e-mail digest.
*/
Schedule::command('notifications:due-soon')->hourly();
Schedule::command('notifications:pending-enrollment-digest')->everyFifteenMinutes();
Schedule::command('notifications:digest')->dailyAt('07:00');
