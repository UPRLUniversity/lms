<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Course, 1: User, 2: User, 3: ForumPost}
     */
    private function forumWithPost(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);

        $author = $this->userWithRole(Role::Student->value);
        $reporter = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $author->id, 'course_id' => $course->id]);
        Enrollment::factory()->active()->create(['user_id' => $reporter->id, 'course_id' => $course->id]);

        $thread = ForumThread::factory()->create(['course_id' => $course->id, 'user_id' => $author->id]);
        $post = $thread->posts()->create(['user_id' => $author->id, 'body' => '<p>A post to report.</p>']);

        return [$course, $reporter, $author, $post];
    }

    public function test_a_member_can_report_another_members_post(): void
    {
        [$course, $reporter, $author, $post] = $this->forumWithPost();

        $this->actingAs($reporter)->post(route('posts.report', $post), [
            'reason' => 'Off topic',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_post_reports', [
            'forum_post_id' => $post->id,
            'user_id' => $reporter->id,
            'reason' => 'Off topic',
        ]);
    }

    public function test_a_member_cannot_report_their_own_post(): void
    {
        [$course, $reporter, $author, $post] = $this->forumWithPost();

        $this->actingAs($author)->post(route('posts.report', $post), ['reason' => 'x'])
            ->assertForbidden();
    }

    public function test_admin_sees_the_queue_and_can_remove_a_reported_post(): void
    {
        [$course, $reporter, $author, $post] = $this->forumWithPost();
        $admin = $this->userWithRole(Role::Admin->value);

        $report = $post->reports()->create(['user_id' => $reporter->id, 'reason' => 'Spam']);

        $this->actingAs($admin)->get(route('admin.forum-reports.index'))
            ->assertOk()
            ->assertSee('Spam');

        $this->actingAs($admin)->post(route('admin.forum-reports.remove', $post))->assertRedirect();

        $this->assertSoftDeleted('forum_posts', ['id' => $post->id]);
        $this->assertDatabaseHas('forum_post_reports', [
            'id' => $report->id,
            'resolved_by' => $admin->id,
        ]);
        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_a_non_admin_cannot_view_the_report_queue(): void
    {
        [$course, $reporter] = $this->forumWithPost();
        $instructor = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($reporter)->get(route('admin.forum-reports.index'))->assertForbidden();
        $this->actingAs($instructor)->get(route('admin.forum-reports.index'))->assertForbidden();
    }
}
