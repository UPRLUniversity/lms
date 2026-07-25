<?php

namespace Tests\Feature\Communication;

use App\Enums\ConversationType;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\Communication\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published course taught by an instructor with two active students.
     *
     * @return array{0: Course, 1: User, 2: User, 3: User}
     */
    private function courseWithMembers(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);

        $studentA = $this->userWithRole(Role::Student->value);
        $studentB = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $studentA->id, 'course_id' => $course->id]);
        Enrollment::factory()->active()->create(['user_id' => $studentB->id, 'course_id' => $course->id]);

        return [$course, $instructor, $studentA, $studentB];
    }

    public function test_a_student_can_message_their_instructor(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        $this->actingAs($student)->post(route('messages.store'), [
            'type' => ConversationType::Direct->value,
            'recipient_id' => $instructor->id,
            'body' => '<p>Hello, a quick question about the essay.</p>',
        ])->assertRedirect();

        $conversation = Conversation::query()->where('type', ConversationType::Direct->value)->first();
        $this->assertNotNull($conversation);
        $this->assertTrue($conversation->hasParticipant($student));
        $this->assertTrue($conversation->hasParticipant($instructor));
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'user_id' => $student->id]);
    }

    public function test_starting_a_direct_conversation_twice_reuses_the_same_thread(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $service = app(MessagingService::class);

        $first = $service->startDirect($student, $instructor);
        $second = $service->startDirect($instructor, $student);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Conversation::query()->where('type', ConversationType::Direct->value)->count());
    }

    public function test_a_student_cannot_message_someone_they_share_no_course_with(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();

        // A student on a different course entirely.
        $otherInstructor = $this->userWithRole(Role::Instructor->value);
        $otherCourse = Course::factory()->published()->create(['created_by' => $otherInstructor->id]);
        $stranger = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $stranger->id, 'course_id' => $otherCourse->id]);

        $this->actingAs($student)->post(route('messages.start', $stranger))->assertForbidden();
    }

    public function test_unread_counts_are_truthful_and_reset_on_open(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $service = app(MessagingService::class);

        $conversation = $service->startDirect($student, $instructor);
        $service->sendMessage($student, $conversation, '<p>Message one.</p>');
        $service->sendMessage($student, $conversation, '<p>Message two.</p>');

        $conversation->load('participants');
        // The recipient sees two unread; the sender sees none.
        $this->assertSame(2, $conversation->unreadCountFor($instructor));
        $this->assertSame(0, $conversation->unreadCountFor($student));
        $this->assertSame(2, $service->totalUnread($instructor));

        // Opening the conversation marks it read.
        $this->actingAs($instructor)->get(route('messages.show', $conversation))->assertOk();

        $conversation->load('participants');
        $this->assertSame(0, $conversation->unreadCountFor($instructor));
        $this->assertSame(0, $service->totalUnread($instructor));
    }

    public function test_a_new_message_notifies_the_recipient_and_dedupes_a_burst(): void
    {
        Notification::fake();
        [$course, $instructor, $student] = $this->courseWithMembers();
        $service = app(MessagingService::class);

        $conversation = $service->startDirect($student, $instructor);
        $service->sendMessage($student, $conversation, '<p>First.</p>');
        // Second message while the first is still unread should NOT re-notify.
        $service->sendMessage($student, $conversation, '<p>Second.</p>');

        Notification::assertSentToTimes($instructor, NewMessageNotification::class, 1);
        Notification::assertNotSentTo($student, NewMessageNotification::class);
    }

    public function test_a_new_message_reaches_the_recipients_bell(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $service = app(MessagingService::class);

        $conversation = $service->startDirect($student, $instructor);
        $service->sendMessage($student, $conversation, '<p>Bell test.</p>');

        // The queued database notification runs inline (sync queue) → lands on the bell.
        $this->assertSame(1, $instructor->fresh()->unreadNotifications()->count());
    }

    public function test_instructor_can_message_all_enrolled_as_a_group(): void
    {
        Notification::fake();
        [$course, $instructor, $studentA, $studentB] = $this->courseWithMembers();

        $this->actingAs($instructor)->post(route('messages.course', $course), [
            'subject' => 'Class reminder',
            'body' => '<p>Live session moved to Thursday.</p>',
        ])->assertRedirect();

        $group = Conversation::query()->where('type', ConversationType::Group->value)->first();
        $this->assertNotNull($group);
        $this->assertSame($course->id, $group->course_id);
        $this->assertTrue($group->hasParticipant($studentA));
        $this->assertTrue($group->hasParticipant($studentB));
        $this->assertTrue($group->hasParticipant($instructor));

        Notification::assertSentTo($studentA, NewMessageNotification::class);
        Notification::assertSentTo($studentB, NewMessageNotification::class);
    }

    public function test_a_student_cannot_create_a_group_conversation(): void
    {
        [$course, $instructor, $student, $studentB] = $this->courseWithMembers();

        $this->actingAs($student)->post(route('messages.store'), [
            'type' => ConversationType::Group->value,
            'subject' => 'My group',
            'participant_ids' => [$studentB->id],
            'body' => '<p>hi</p>',
        ])->assertForbidden();
    }

    public function test_a_non_participant_cannot_read_or_post_to_a_conversation(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $service = app(MessagingService::class);
        $conversation = $service->startDirect($student, $instructor);

        $outsider = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $outsider->id, 'course_id' => $course->id]);

        $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)->post(route('messages.send', $conversation), ['body' => '<p>intruding</p>'])
            ->assertForbidden();
    }

    public function test_the_auditor_cannot_use_messaging(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $target = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($auditor)->get(route('messages.index'))->assertForbidden();
        $this->actingAs($auditor)->post(route('messages.start', $target))->assertForbidden();
    }

    public function test_message_body_is_sanitized_on_save(): void
    {
        [$course, $instructor, $student] = $this->courseWithMembers();
        $service = app(MessagingService::class);
        $conversation = $service->startDirect($student, $instructor);

        $this->actingAs($student)->post(route('messages.send', $conversation), [
            'body' => '<p>safe</p><script>alert(1)</script>',
        ])->assertRedirect();

        $stored = $conversation->messages()->latest('id')->first();
        $this->assertStringNotContainsString('<script', (string) $stored->body);
        $this->assertStringContainsString('safe', (string) $stored->body);
    }
}
