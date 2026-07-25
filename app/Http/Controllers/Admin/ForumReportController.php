<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumPost;
use App\Models\ForumPostReport;
use App\Services\Communication\ForumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The admin moderation queue for reported forum posts. Reporting is open to any member
 * (a moderation hook — profanity filtering itself is out of scope, see docs/decisions);
 * acting on a report is admin-only. An admin can dismiss a report (nothing wrong) or
 * remove the post (which also resolves its reports).
 */
class ForumReportController extends Controller
{
    public function __construct(private readonly ForumService $forum) {}

    public function index(): View
    {
        Gate::authorize('reviewForumReports');

        $reports = ForumPostReport::query()
            ->open()
            ->with(['reporter', 'post.author', 'post.thread.course'])
            ->latest()
            ->paginate(20);

        return view('admin.forum-reports.index', [
            'reports' => $reports,
            'openCount' => $reports->total(),
        ]);
    }

    /**
     * Dismiss a report — the post is fine, close the flag.
     */
    public function dismiss(ForumPostReport $report): RedirectResponse
    {
        Gate::authorize('reviewForumReports');

        $this->forum->resolveReport($report, request()->user());

        return back()->with('status', 'Report dismissed.');
    }

    /**
     * Uphold a report — remove the offending post and resolve every open report on it.
     */
    public function removePost(ForumPost $post): RedirectResponse
    {
        Gate::authorize('reviewForumReports');

        $this->forum->deletePost($post);

        $post->reports()->whereNull('resolved_at')->update([
            'resolved_at' => now(),
            'resolved_by' => request()->user()->id,
        ]);

        return back()->with('status', 'Post removed and reports closed.');
    }
}
