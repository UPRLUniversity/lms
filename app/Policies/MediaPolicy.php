<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Message;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class MediaPolicy
{
    /**
     * Who may download a private media file. The uploader always may. Beyond that,
     * access follows the owning model's own policy rather than re-deriving it here:
     * lesson files → whoever may learn the lesson; assignment resources → whoever may
     * submit (or manage/audit) the assignment; submission files → whoever may view
     * that submission version (the student, the graders, the auditor).
     */
    public function view(User $user, Media $media): bool
    {
        if ($media->uploaded_by !== null && $media->uploaded_by === $user->id) {
            return true;
        }

        $owner = $media->mediable;

        if ($owner instanceof Lesson) {
            return Gate::forUser($user)->allows('learn', $owner);
        }

        if ($owner instanceof Assignment) {
            return Gate::forUser($user)->allows('submit', $owner)
                || Gate::forUser($user)->allows('view', $owner);
        }

        if ($owner instanceof Submission) {
            return Gate::forUser($user)->allows('view', $owner);
        }

        // A message attachment: any participant of the conversation the message
        // belongs to (the same rule as reading the message itself).
        if ($owner instanceof Message) {
            return Gate::forUser($user)->allows('view', $owner->conversation);
        }

        return false;
    }
}
