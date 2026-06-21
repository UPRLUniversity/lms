<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class MediaPolicy
{
    /**
     * Who may download a private media file. The uploader always may. Beyond that,
     * files attached to a lesson (its primary file or downloadable resources) are
     * viewable by anyone who may learn that lesson — enrolled students and staff
     * previewing — reusing the LessonPolicy@learn rule rather than re-deriving access.
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

        return false;
    }
}
