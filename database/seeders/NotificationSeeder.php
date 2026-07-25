<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\AssignmentDueSoonNotification;
use App\Notifications\AssignmentGradedNotification;
use App\Notifications\AssignmentReturnedNotification;
use App\Notifications\AttemptGradedNotification;
use App\Notifications\CertificateIssuedNotification;
use App\Notifications\CourseAnnouncementNotification;
use App\Notifications\CourseSubmittedForReviewNotification;
use App\Notifications\EnrollmentApprovedNotification;
use App\Notifications\NewSubmissionNotification;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Runs LAST. Most of the catalogue already fires naturally through the seeded data
 * above — every genuine course completion issues a real CertificateIssuedNotification
 * through the CourseCompleted pipeline (see CertificateSeeder), and enrolment/course
 * status changes only ever happen through their services in real use. AssignmentSeeder
 * and the enrolment/course seeders write rows directly for idempotent speed, so THIS
 * step covers the remaining catalogue entries the same way CertificateSeeder "tops up" —
 * against real, already-seeded rows, never synthetic fixtures — so the bell and
 * /notifications page are a convincing, clickable demo out of the box.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $this->announceOnAnActiveCourse();
        $this->notifyOnAGradedSubmission();
        $this->notifyOnAReturnedSubmission();
        $this->notifyInstructorsOfAQueuedSubmission();
        $this->notifyAdminsOfACourseInReview();
        $this->markRoughlyHalfOfEachInboxRead();
        $this->seedShowcaseInbox();
    }

    /**
     * Give the demo student account (student1) a fuller, varied inbox spread across a
     * few days, so the bell and the /notifications timeline are a convincing showcase
     * on a fresh seed. These are presentation rows written straight to the notifications
     * table (a stored notification only carries its type + a data payload), each with a
     * real catalogue type so the icon, tone and deep link render exactly as a live one.
     */
    private function seedShowcaseInbox(): void
    {
        $student = User::where('email', 'student1@uprl.test')->first();
        if (! $student) {
            return;
        }

        // Clear any earlier rows so the showcase is deterministic on a re-seed.
        $student->notifications()->delete();

        $learn = route('learning.index');
        $certs = route('certificates.mine');

        $rows = [
            [CertificateIssuedNotification::class, 'Certificate issued', 'Your certificate for "PRL101: Foundations of Public Relations" is ready.', $certs, false, now()->subMinutes(15)],
            [AttemptGradedNotification::class, 'Exam graded', '"Final exam" has been graded — 82%.', $learn, false, now()->subHours(3)],
            [CourseAnnouncementNotification::class, 'New announcement: Welcome to the course', 'PRL101: Foundations of Public Relations', $learn, true, now()->subHours(6)],
            [AssignmentGradedNotification::class, 'Assignment graded', '"Case study: crisis response" has been graded.', $learn, true, now()->subDay()->setTime(14, 20)],
            [EnrollmentApprovedNotification::class, 'Enrollment approved', "You're in — \"Organisational Leadership\" has been approved.", $learn, true, now()->subDays(3)->setTime(9, 5)],
            [AssignmentDueSoonNotification::class, 'Assignment due soon', '"Reflection essay" is due in about 2 days.', $learn, true, now()->subDays(5)->setTime(8, 0)],
        ];

        foreach ($rows as [$type, $title, $body, $url, $read, $at]) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'data' => ['title' => $title, 'body' => $body, 'url' => $url],
                'read_at' => $read ? $at->copy()->addMinutes(30) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function announceOnAnActiveCourse(): void
    {
        $course = Course::query()
            ->published()
            ->whereHas('enrollments', fn ($q) => $q->where('status', EnrollmentStatus::Active->value))
            ->with('instructors')
            ->first();

        if (! $course) {
            return;
        }

        $author = $course->instructors->first() ?? $course->creator;
        if (! $author) {
            return;
        }

        $announcement = CourseAnnouncement::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Welcome to the course'],
            [
                'user_id' => $author->id,
                'body' => '<p>Glad to have you here — check the curriculum for what\'s ahead, '
                    .'and reach out if you have any questions.</p>',
            ],
        );

        $students = User::query()
            ->whereHas('enrollments', fn ($q) => $q->where('course_id', $course->id)
                ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value]))
            ->get();

        Notification::send($students, new CourseAnnouncementNotification($announcement));
    }

    private function notifyOnAGradedSubmission(): void
    {
        $submission = Submission::query()->where('status', SubmissionStatus::Graded->value)
            ->with('user', 'assignment')->first();

        $submission?->user->notify(new AssignmentGradedNotification($submission));
    }

    private function notifyOnAReturnedSubmission(): void
    {
        $submission = Submission::query()->where('status', SubmissionStatus::ReturnedForResubmission->value)
            ->with('user', 'assignment')->first();

        $submission?->user->notify(new AssignmentReturnedNotification($submission));
    }

    private function notifyInstructorsOfAQueuedSubmission(): void
    {
        $submission = Submission::query()->where('status', SubmissionStatus::Submitted->value)
            ->with('assignment.course.instructors')->first();

        if ($submission) {
            Notification::send($submission->assignment->course->instructors, new NewSubmissionNotification($submission));
        }
    }

    private function notifyAdminsOfACourseInReview(): void
    {
        $course = Course::query()->where('status', CourseStatus::Review->value)->first();

        if ($course) {
            $admins = User::role([Role::Admin->value, Role::SuperAdmin->value])->get();
            Notification::send($admins, new CourseSubmittedForReviewNotification($course));
        }
    }

    /**
     * A mixed read/unread inbox reads as a lived-in account rather than a fresh one.
     */
    private function markRoughlyHalfOfEachInboxRead(): void
    {
        DatabaseNotification::query()->get()
            ->groupBy('notifiable_id')
            ->each(function ($inbox) {
                $inbox->sortBy('created_at')
                    ->take(intdiv($inbox->count(), 2))
                    ->each(fn (DatabaseNotification $n) => $n->update(['read_at' => now()]));
            });
    }
}
