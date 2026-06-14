<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Who may download a private media file. For now: the uploader. Feature
     * sections layer on role checks (e.g. an instructor grading a submission, an
     * admin/auditor) by extending this single policy — not by inlining checks.
     */
    public function view(User $user, Media $media): bool
    {
        return $media->uploaded_by !== null && $media->uploaded_by === $user->id;
    }
}
