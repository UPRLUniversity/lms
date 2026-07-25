<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreThreadRequest;
use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Lesson;
use App\Services\Communication\ForumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A course's discussion forum: the thread list (pinned first, unanswered filter,
 * optional lesson scope), a single thread with its replies, opening a thread, and the
 * instructor moderation actions (pin / lock / accept answer / remove). Access is gated
 * by ForumPolicy (course membership); each action re-authorizes at its own level.
 */
class ForumController extends Controller
{
    public function __construct(private readonly ForumService $forum) {}

    /**
     * The thread list. `filter=unanswered` narrows to open questions; `lesson=<id>`
     * scopes to a lesson's discussion (the "Discuss this lesson" entry point).
     */
    public function index(Request $request, Course $course): View
    {
        $this->authorize('accessForum', $course);

        $filter = $request->query('filter', 'all');
        $lessonId = $request->integer('lesson') ?: null;

        $threads = $course->forumThreads()
            ->with(['author', 'lesson', 'answer'])
            ->withCount('posts')
            ->when($filter === 'unanswered', fn ($q) => $q->unanswered())
            ->when($lessonId, fn ($q) => $q->where('lesson_id', $lessonId))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('learn.forum.index', [
            'course' => $course,
            'threads' => $threads,
            'filter' => $filter,
            'lesson' => $lessonId ? $course->lessons()->find($lessonId) : null,
            'canParticipate' => $request->user()->can('participateInForum', $course),
            'canModerate' => $request->user()->can('moderateForum', $course),
        ]);
    }

    /**
     * The new-thread form. A `lesson` query prefills the "Discuss this lesson" link.
     */
    public function create(Request $request, Course $course): View
    {
        $this->authorize('participateInForum', $course);

        $lessonId = $request->integer('lesson') ?: null;

        return view('learn.forum.create', [
            'course' => $course,
            'lesson' => $lessonId ? $course->lessons()->find($lessonId) : null,
        ]);
    }

    public function store(StoreThreadRequest $request, Course $course): RedirectResponse
    {
        $data = $request->validated();

        // A lesson link must belong to this course, else drop it.
        $lessonId = $data['lesson_id'] ?? null;
        if ($lessonId && ! $course->lessons()->whereKey($lessonId)->exists()) {
            $lessonId = null;
        }

        $thread = $this->forum->createThread($request->user(), $course, [
            'title' => $data['title'],
            'body' => $data['body'],
            'lesson_id' => $lessonId,
        ]);

        return redirect()
            ->route('forum.show', [$course, $thread])
            ->with('status', 'Your discussion has been posted.');
    }

    /**
     * A single thread with its replies (each reply's own replies included, one level).
     */
    public function show(Request $request, Course $course, ForumThread $thread): View
    {
        abort_unless($thread->course_id === $course->id, 404);
        $this->authorize('view', $thread);

        $thread->load([
            'author',
            'lesson',
            'answer.author',
            'posts' => fn ($q) => $q->whereNull('parent_id')->with(['author', 'replies.author']),
        ]);

        return view('learn.forum.show', [
            'course' => $course,
            'thread' => $thread,
            'canReply' => $request->user()->can('reply', $thread),
            'canModerate' => $request->user()->can('moderateForum', $course),
            'canMarkAnswer' => $request->user()->can('markAnswer', $thread),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Moderation & answer (instructors/admins, or the thread author for answers)
    |--------------------------------------------------------------------------
    */

    public function pin(Course $course, ForumThread $thread): RedirectResponse
    {
        abort_unless($thread->course_id === $course->id, 404);
        $this->authorize('moderate', $thread);

        $pinned = $this->forum->togglePin($thread);

        return back()->with('status', $pinned ? 'Thread pinned.' : 'Thread unpinned.');
    }

    public function lock(Course $course, ForumThread $thread): RedirectResponse
    {
        abort_unless($thread->course_id === $course->id, 404);
        $this->authorize('moderate', $thread);

        $locked = $this->forum->toggleLock($thread);

        return back()->with('status', $locked ? 'Thread locked — no new replies.' : 'Thread unlocked.');
    }

    /**
     * Accept a reply as the answer (or clear it when the posted id is already the
     * answer). The thread author or an instructor may do this.
     */
    public function answer(Request $request, Course $course, ForumThread $thread): RedirectResponse
    {
        abort_unless($thread->course_id === $course->id, 404);
        $this->authorize('markAnswer', $thread);

        $postId = (int) $request->integer('post_id');
        $post = ForumPost::where('forum_thread_id', $thread->id)->find($postId);

        abort_unless($post !== null, 404);

        if ($thread->answer_post_id === $post->id) {
            $this->forum->clearAnswer($thread);

            return back()->with('status', 'Answer cleared.');
        }

        $this->forum->markAnswer($thread, $post);

        return back()->with('status', 'Marked as the answer.');
    }

    public function destroy(Course $course, ForumThread $thread): RedirectResponse
    {
        abort_unless($thread->course_id === $course->id, 404);
        $this->authorize('delete', $thread);

        $this->forum->deleteThread($thread);

        return redirect()
            ->route('forum.index', $course)
            ->with('status', 'Thread removed.');
    }
}
