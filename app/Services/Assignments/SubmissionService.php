<?php

namespace App\Services\Assignments;

use App\Enums\MediaPurpose;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\NewSubmissionNotification;
use App\Services\Media\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place submissions are created. Versioning by construction: every hand-in
 * INSERTS version max+1 — nothing ever updates a prior version's content, so history
 * is immutable without any extra machinery. Late policy is enforced here
 * server-side; the due-date UI is only a courtesy.
 */
class SubmissionService
{
    public function __construct(private readonly PrivateFileService $privateFiles) {}

    /**
     * Hand in a new version. $files are stored on the private disk and attached to the
     * new Submission; $body is sanitized by the model cast.
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws ValidationException when the deadline has passed and late work is not accepted
     */
    public function submit(Assignment $assignment, User $user, ?string $body, array $files = []): Submission
    {
        $isLate = $assignment->isPastDue();

        if ($isLate && ! $assignment->allow_late) {
            throw ValidationException::withMessages([
                'submission' => 'The deadline for this assignment has passed and late submissions aren\'t accepted. '
                    .'If you believe this is an error, please contact your instructor.',
            ]);
        }

        $this->assertContentMatchesType($assignment, $body, $files);

        $submission = DB::transaction(function () use ($assignment, $user, $body, $isLate) {
            $version = (int) $assignment->submissions()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->max('version') + 1;

            return $assignment->submissions()->create([
                'user_id' => $user->id,
                'version' => $version,
                'body' => $body,
                'submitted_at' => now(),
                'is_late' => $isLate,
                'status' => SubmissionStatus::Submitted->value,
            ]);
        });

        // Store files after the row exists so each Media attaches to its version.
        // PrivateFileService re-validates mime + size server-side.
        foreach ($files as $file) {
            $this->privateFiles->store($file, MediaPurpose::Submissions, $submission);
        }

        Notification::send($assignment->course->instructors, new NewSubmissionNotification($submission));

        return $submission;
    }

    /**
     * A submission must carry the kind of content the assignment asks for.
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws ValidationException
     */
    private function assertContentMatchesType(Assignment $assignment, ?string $body, array $files): void
    {
        $hasText = trim(strip_tags((string) $body)) !== '';
        $hasFiles = $files !== [];

        if (! $assignment->type->acceptsFiles() && $hasFiles) {
            throw ValidationException::withMessages([
                'files' => 'This assignment takes a typed answer only — file uploads aren\'t accepted here.',
            ]);
        }

        if (! $assignment->type->acceptsText() && $hasText) {
            throw ValidationException::withMessages([
                'body' => 'This assignment takes a file upload only — please attach your work as a file.',
            ]);
        }

        if (! $hasText && ! $hasFiles) {
            throw ValidationException::withMessages([
                'submission' => match ($assignment->type->value) {
                    'file' => 'Attach at least one file to submit.',
                    'text' => 'Write your answer before submitting.',
                    default => 'Write your answer or attach a file before submitting.',
                },
            ]);
        }
    }
}
