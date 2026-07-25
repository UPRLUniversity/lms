<?php

namespace App\Policies;

use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Instance-level authorization for a single thread (auto-discovered). Course-level
 * reach is delegated to ForumPolicy's named gates; this layer adds the per-thread
 * rules: a locked thread accepts no new replies, and marking an answer is the thread
 * author's or an instructor's call.
 */
class ForumThreadPolicy
{
    /**
     * Read a thread and its replies — anyone who may access the course forum.
     */
    public function view(User $user, ForumThread $thread): bool
    {
        return Gate::forUser($user)->allows('accessForum', $thread->course);
    }

    /**
     * Post a reply: a forum participant, and only while the thread is unlocked.
     * Moderators (instructors/admins) may still reply to a locked thread.
     */
    public function reply(User $user, ForumThread $thread): bool
    {
        if (! Gate::forUser($user)->allows('participateInForum', $thread->course)) {
            return false;
        }

        if ($thread->isLocked()) {
            return Gate::forUser($user)->allows('moderateForum', $thread->course);
        }

        return true;
    }

    /**
     * Accept (or clear) the answer — the thread's own author, or an instructor/admin.
     */
    public function markAnswer(User $user, ForumThread $thread): bool
    {
        return $thread->user_id === $user->id
            || Gate::forUser($user)->allows('moderateForum', $thread->course);
    }

    /**
     * Pin, lock and remove are moderation — instructors on their course, admins on all.
     */
    public function moderate(User $user, ForumThread $thread): bool
    {
        return Gate::forUser($user)->allows('moderateForum', $thread->course);
    }

    public function delete(User $user, ForumThread $thread): bool
    {
        // The author may retract their own thread; moderators may remove any.
        return $thread->user_id === $user->id
            || Gate::forUser($user)->allows('moderateForum', $thread->course);
    }
}
