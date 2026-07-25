<?php

namespace App\Services\Communication;

use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumPostReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Notifications\ForumReplyNotification;
use Illuminate\Support\Facades\DB;

/**
 * All forum write logic: opening threads, replying (one level of nesting), moderation
 * (pin/lock/remove) and accepting an answer. Controllers stay thin — they authorize
 * and shape the response; the rules and side effects (activity bumping, notifying the
 * thread author, keeping answer_post_id consistent) live here.
 */
class ForumService
{
    /**
     * Open a new thread. An optional lesson scopes it to "Discuss this lesson".
     *
     * @param  array{title: string, body: string, lesson_id?: int|null}  $data
     */
    public function createThread(User $author, Course $course, array $data): ForumThread
    {
        return $course->forumThreads()->create([
            'user_id' => $author->id,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Post a reply. Nesting is capped at one level: replying to a reply attaches to its
     * top-level parent instead. Bumps the thread's activity and notifies its author
     * (unless they're the one replying).
     */
    public function reply(User $author, ForumThread $thread, string $body, ?ForumPost $parent = null): ForumPost
    {
        // Flatten to one level: a reply's parent is always a top-level post.
        if ($parent && $parent->parent_id !== null) {
            $parent = $parent->parent()->first() ?? $parent;
        }

        $post = $thread->posts()->create([
            'user_id' => $author->id,
            'parent_id' => $parent?->id,
            'body' => $body,
        ]);

        $thread->forceFill(['last_activity_at' => now()])->save();

        // Tell the thread's author an answer arrived — but not for their own reply.
        if ($thread->user_id !== $author->id) {
            $thread->author?->notify(new ForumReplyNotification($post));
        }

        return $post;
    }

    /**
     * Accept a post as the thread's answer (flags the thread "Answered"). The post must
     * belong to the thread.
     */
    public function markAnswer(ForumThread $thread, ForumPost $post): void
    {
        if ($post->forum_thread_id !== $thread->id) {
            return;
        }

        $thread->forceFill(['answer_post_id' => $post->id])->save();
    }

    /**
     * Clear the accepted answer (back to unanswered).
     */
    public function clearAnswer(ForumThread $thread): void
    {
        $thread->forceFill(['answer_post_id' => null])->save();
    }

    public function togglePin(ForumThread $thread): bool
    {
        $thread->forceFill(['is_pinned' => ! $thread->is_pinned])->save();

        return $thread->is_pinned;
    }

    public function toggleLock(ForumThread $thread): bool
    {
        $thread->forceFill(['is_locked' => ! $thread->is_locked])->save();

        return $thread->is_locked;
    }

    /**
     * Soft-remove a post (moderation). If it was the thread's accepted answer, clear
     * that link so the thread reads as unanswered again.
     */
    public function deletePost(ForumPost $post): void
    {
        DB::transaction(function () use ($post) {
            if ($post->thread && $post->thread->answer_post_id === $post->id) {
                $post->thread->forceFill(['answer_post_id' => null])->save();
            }

            $post->delete();
        });
    }

    public function deleteThread(ForumThread $thread): void
    {
        $thread->delete();
    }

    /**
     * Flag a post for admin review. One open report per user per post — reporting again
     * just refreshes the reason.
     */
    public function report(User $reporter, ForumPost $post, ?string $reason = null): ForumPostReport
    {
        return ForumPostReport::updateOrCreate(
            ['forum_post_id' => $post->id, 'user_id' => $reporter->id],
            ['reason' => $reason, 'resolved_at' => null, 'resolved_by' => null],
        );
    }

    /**
     * Mark a report handled (an admin has looked at it).
     */
    public function resolveReport(ForumPostReport $report, User $admin): void
    {
        $report->forceFill(['resolved_at' => now(), 'resolved_by' => $admin->id])->save();
    }
}
