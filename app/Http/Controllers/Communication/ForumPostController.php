<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ReportPostRequest;
use App\Http\Requests\Communication\StorePostRequest;
use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Services\Communication\ForumService;
use Illuminate\Http\RedirectResponse;

/**
 * Replies within a thread: posting one (authorization + lock enforced by StorePostRequest
 * → the 'reply' ability), removing one (author or moderator), and flagging one for admin
 * review. All the write rules live in ForumService.
 */
class ForumPostController extends Controller
{
    public function __construct(private readonly ForumService $forum) {}

    public function store(StorePostRequest $request, Course $course, ForumThread $thread): RedirectResponse
    {
        abort_unless($thread->course_id === $course->id, 404);

        // A reply target (parent) must belong to this thread, else treat as top-level.
        $parent = null;
        if ($parentId = $request->integer('parent_id')) {
            $parent = ForumPost::where('forum_thread_id', $thread->id)->find($parentId);
        }

        $post = $this->forum->reply($request->user(), $thread, $request->validated('body'), $parent);

        return redirect()
            ->route('forum.show', [$course, $thread])
            ->withFragment('post-'.$post->id)
            ->with('status', 'Reply posted.');
    }

    public function destroy(ForumPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $thread = $post->thread()->with('course')->first();
        $this->forum->deletePost($post);

        return redirect()
            ->route('forum.show', [$thread->course, $thread])
            ->with('status', 'Reply removed.');
    }

    public function report(ReportPostRequest $request, ForumPost $post): RedirectResponse
    {
        $this->forum->report($request->user(), $post, $request->validated('reason'));

        return back()->with('status', 'Thanks — this post has been flagged for review.');
    }
}
