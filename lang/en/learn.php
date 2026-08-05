<?php

/*
|--------------------------------------------------------------------------
| Learner-facing strings — the player, progress, certificates
|--------------------------------------------------------------------------
|
| The tone rule from CLAUDE.md carries into translation: buttons are VERBS
| ("Continue learning"), never nouns, and the voice stays warm and encouraging.
| A translator should be told this — see docs/localization.md.
|
*/

return [

    // The player.
    'continue_learning' => 'Continue learning',
    'start_learning' => 'Start learning',
    'mark_complete' => 'Mark as complete',
    'mark_incomplete' => 'Mark as not complete',
    'next_lesson' => 'Next lesson',
    'previous_lesson' => 'Previous lesson',
    'back_to_course' => 'Back to the course',
    'course_content' => 'Course content',
    'lesson_of' => 'Lesson :current of :total',

    // Progress.
    'your_progress' => 'Your progress',
    'percent_complete' => ':percent% complete',
    'completed' => 'Completed',
    'in_progress' => 'In progress',
    'not_started' => 'Not started',
    'required' => 'Required',
    'optional' => 'Optional',
    'counts_toward_grade' => 'Counts toward your grade',

    // Assessment & assignment.
    'start_attempt' => 'Start the assessment',
    'resume_attempt' => 'Resume where you left off',
    'submit_for_grading' => 'Submit for grading',
    'review_answers' => 'Review your answers',
    'attempts_remaining' => '{0} No attempts remaining|{1} 1 attempt remaining|[2,*] :count attempts remaining',
    'time_remaining' => 'Time remaining',
    'submit_assessment' => 'Submit assessment',

    // Completion & certificates.
    'congratulations' => 'Congratulations!',
    'course_complete' => "You've completed this course.",
    'download_certificate' => 'Download your certificate',
    'certificate_preparing' => 'Your certificate is being prepared…',
    'view_grades' => 'View your grades',

    // Enrolment.
    'enrol_now' => 'Enrol now',
    'request_enrolment' => 'Request a place',
    'withdraw' => 'Withdraw from this course',
    'awaiting_approval' => 'Awaiting approval',
    'on_waitlist' => "You're on the waiting list",

    // Empty states.
    'no_courses_yet' => 'No courses yet',
    'no_courses_hint' => 'Browse the catalogue to find your first paper.',
    'browse_catalogue' => 'Browse the catalogue',
];
