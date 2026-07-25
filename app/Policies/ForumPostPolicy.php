<?php

namespace App\Policies;

use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Instance-level authorization for a single reply (auto-discovered). Removal is either
 * the author retracting their own post or a moderator taking it down; reporting is
 * open to any member except the post's own author.
 */
class ForumPostPolicy
{
    /**
     * Remove a post — its author (retract) or a course moderator (instructor/admin).
     */
    public function delete(User $user, ForumPost $post): bool
    {
        if ($post->user_id === $user->id) {
            return true;
        }

        return Gate::forUser($user)->allows('moderateForum', $post->thread->course);
    }

    /**
     * Flag a post for admin review — any forum participant, but not your own post
     * (nothing to report about yourself).
     */
    public function report(User $user, ForumPost $post): bool
    {
        if ($post->user_id === $user->id) {
            return false;
        }

        return Gate::forUser($user)->allows('accessForum', $post->thread->course);
    }
}
