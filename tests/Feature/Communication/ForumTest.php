<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ForumThread;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course taught by a fresh instructor, plus one active student.
     *
     * @return array{0: Course, 1: User, 2: User}
     */
    private function courseWithMembers(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        return [$course, $instructor, $student];
    }

    public function test_enrolled_student_can_view_the_forum_and_open_a_thread(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        $thread = ForumThread::factory()->create([
            'course_id' => $course->id,
            'user_id' => $student->id,
            'title' => 'A question about PR ethics',
        ]);

        $this->actingAs($student)->get(route('forum.index', $course))
            ->assertOk()
            ->assertSee('A question about PR ethics');

        $this->actingAs($student)->get(route('forum.show', [$course, $thread]))
            ->assertOk()
            ->assertSee('A question about PR ethics');
    }

    public function test_non_enrolled_user_cannot_access_the_forum_or_its_threads(): void
    {
        [$course, $instructor] = $this->courseWithMembers();
        $outsider = $this->userWithRole(Role::Student->value);

        $thread = ForumThread::factory()->create(['course_id' => $course->id, 'user_id' => $course->created_by]);

        $this->actingAs($outsider)->get(route('forum.index', $course))->assertForbidden();
        $this->actingAs($outsider)->get(route('forum.show', [$course, $thread]))->assertForbidden();
        $this->actingAs($outsider)->post(route('forum.store', $course), [
            'title' => 'Sneaky', 'body' => '<p>hi</p>',
        ])->assertForbidden();
    }

    public function test_auditor_may_read_the_forum_but_never_post(): void
    {
        [$course] = $this->courseWithMembers();
        $auditor = $this->userWithRole(Role::Auditor->value);

        $this->actingAs($auditor)->get(route('forum.index', $course))->assertOk();

        $this->actingAs($auditor)->post(route('forum.store', $course), [
            'title' => 'Observer note', 'body' => '<p>hi</p>',
        ])->assertForbidden();
    }

    public function test_student_can_open_a_thread_and_reply(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        $this->actingAs($student)->post(route('forum.store', $course), [
            'title' => 'How do I cite a press release?',
            'body' => '<p>Not sure of the format.</p>',
        ])->assertRedirect();

        $thread = ForumThread::where('course_id', $course->id)->firstWhere('title', 'How do I cite a press release?');
        $this->assertNotNull($thread);

        $this->actingAs($instructor)->post(route('posts.store', [$course, $thread]), [
            'body' => '<p>Use the APA press-release format.</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_posts', [
            'forum_thread_id' => $thread->id,
            'user_id' => $instructor->id,
        ]);
    }

    public function test_student_asks_instructor_replies_and_reply_is_marked_as_the_answer(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        $thread = ForumThread::factory()->create([
            'course_id' => $course->id, 'user_id' => $student->id, 'title' => 'Framing vs priming?',
        ]);

        // Instructor replies.
        $this->actingAs($instructor)->post(route('posts.store', [$course, $thread]), [
            'body' => '<p>Framing is the angle; priming is the yardstick.</p>',
        ])->assertRedirect();

        $answer = $thread->posts()->first();

        // The thread author accepts it as the answer.
        $this->actingAs($student)->post(route('forum.answer', [$course, $thread]), [
            'post_id' => $answer->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('forum_threads', [
            'id' => $thread->id,
            'answer_post_id' => $answer->id,
        ]);

        // The thread now reads as Answered on the list.
        $this->actingAs($student)->get(route('forum.index', $course))->assertSee('Answered');
    }

    public function test_a_different_student_cannot_mark_the_answer(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $other = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $other->id, 'course_id' => $course->id]);

        $thread = ForumThread::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        $post = $thread->posts()->create(['user_id' => $instructor->id, 'body' => '<p>An answer.</p>']);

        $this->actingAs($other)->post(route('forum.answer', [$course, $thread]), ['post_id' => $post->id])
            ->assertForbidden();
    }

    public function test_locked_thread_rejects_new_student_replies_but_a_moderator_may_still_post(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        $thread = ForumThread::factory()->locked()->create(['course_id' => $course->id, 'user_id' => $student->id]);

        $this->actingAs($student)->post(route('posts.store', [$course, $thread]), [
            'body' => '<p>Can I still reply?</p>',
        ])->assertForbidden();

        $this->actingAs($instructor)->post(route('posts.store', [$course, $thread]), [
            'body' => '<p>Moderator note.</p>',
        ])->assertRedirect();
    }

    public function test_pin_and_lock_are_instructor_only(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $thread = ForumThread::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);

        // Student is refused.
        $this->actingAs($student)->post(route('forum.pin', [$course, $thread]))->assertForbidden();
        $this->actingAs($student)->post(route('forum.lock', [$course, $thread]))->assertForbidden();

        // Instructor toggles both on.
        $this->actingAs($instructor)->post(route('forum.pin', [$course, $thread]))->assertRedirect();
        $this->actingAs($instructor)->post(route('forum.lock', [$course, $thread]))->assertRedirect();

        $thread->refresh();
        $this->assertTrue($thread->isPinned());
        $this->assertTrue($thread->isLocked());
    }

    public function test_removing_the_answer_post_clears_the_answered_state(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $thread = ForumThread::factory()->create(['course_id' => $course->id, 'user_id' => $student->id]);
        $post = $thread->posts()->create(['user_id' => $instructor->id, 'body' => '<p>Answer.</p>']);
        $thread->forceFill(['answer_post_id' => $post->id])->save();

        // Instructor moderates the post away.
        $this->actingAs($instructor)->delete(route('posts.destroy', $post))->assertRedirect();

        $thread->refresh();
        $this->assertNull($thread->answer_post_id);
        $this->assertSoftDeleted('forum_posts', ['id' => $post->id]);
    }

    public function test_thread_and_post_bodies_are_sanitized_on_save(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        $this->actingAs($student)->post(route('forum.store', $course), [
            'title' => 'XSS attempt',
            'body' => '<p>hello</p><script>alert(1)</script>',
        ])->assertRedirect();

        $thread = ForumThread::where('course_id', $course->id)->firstWhere('title', 'XSS attempt');
        $this->assertStringNotContainsString('<script', (string) $thread->body);
        $this->assertStringContainsString('hello', (string) $thread->body);

        $this->actingAs($student)->post(route('posts.store', [$course, $thread]), [
            'body' => '<p>ok</p><script>steal()</script>',
        ])->assertRedirect();

        $post = $thread->posts()->latest('id')->first();
        $this->assertStringNotContainsString('<script', (string) $post->body);
    }

    public function test_discuss_this_lesson_scopes_the_forum_to_a_lesson(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        // A lesson-scoped thread.
        $this->actingAs($student)->post(route('forum.store', $course), [
            'title' => 'About this specific lesson',
            'body' => '<p>Question on the lesson.</p>',
            'lesson_id' => $lesson->id,
        ])->assertRedirect();

        // A general thread (no lesson).
        ForumThread::factory()->create(['course_id' => $course->id, 'user_id' => $student->id, 'title' => 'General chatter']);

        $scoped = ForumThread::where('course_id', $course->id)->firstWhere('title', 'About this specific lesson');
        $this->assertEquals($lesson->id, $scoped->lesson_id);

        $this->actingAs($student)->get(route('forum.index', ['course' => $course, 'lesson' => $lesson->id]))
            ->assertOk()
            ->assertSee('About this specific lesson')
            ->assertDontSee('General chatter');
    }
}
