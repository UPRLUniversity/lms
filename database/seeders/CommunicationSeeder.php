<?php

namespace Database\Seeders;

use App\Enums\ConversationType;
use App\Enums\EnrollmentStatus;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A clickable Section-9 demo: a lived-in course forum (pinned, answered and locked
 * threads with real replies) plus a couple of conversations — a direct student↔
 * instructor thread and a course "message all enrolled" group. Written straight to the
 * tables (idempotent) so a re-seed is deterministic and doesn't depend on the queue.
 */
class CommunicationSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()
            ->published()
            ->whereHas('enrollments', fn ($q) => $q->where('status', EnrollmentStatus::Active->value))
            ->with('instructors')
            ->first();

        if (! $course) {
            return;
        }

        $instructor = $course->instructors->first() ?? $course->creator;
        $students = User::query()
            ->whereHas('enrollments', fn ($q) => $q->where('course_id', $course->id)
                ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value]))
            ->limit(3)
            ->get();

        if (! $instructor || $students->count() < 1) {
            return;
        }

        $asker = $students->first();
        $classmate = $students->get(1) ?? $asker;

        $this->seedForum($course, $instructor, $asker, $classmate);
        $this->seedDirectConversation($course, $instructor, $asker);
        $this->seedCourseGroup($course, $instructor, $students);
    }

    private function seedForum(Course $course, User $instructor, User $asker, User $classmate): void
    {
        // An answered question: student asks → instructor answers → marked as the answer.
        $question = ForumThread::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'How is framing different from priming?'],
            [
                'user_id' => $asker->id,
                'body' => '<p>I keep mixing these two up when I read the case studies. Could someone explain the difference with an example?</p>',
                'last_activity_at' => now()->subHours(20),
                'created_at' => now()->subDay(),
            ],
        );

        if ($question->posts()->count() === 0) {
            $classmate->is($asker) ?: $question->posts()->create([
                'user_id' => $classmate->id,
                'body' => '<p>Good question — I think framing is about how a story is presented, but I\'m not sure about priming.</p>',
                'created_at' => now()->subHours(22),
                'updated_at' => now()->subHours(22),
            ]);

            $answer = $question->posts()->create([
                'user_id' => $instructor->id,
                'body' => '<p><strong>Framing</strong> is choosing which angle of an issue to emphasise; <strong>priming</strong> is shaping the criteria your audience later uses to judge it. Framing is about the message, priming about the yardstick.</p>',
                'created_at' => now()->subHours(20),
                'updated_at' => now()->subHours(20),
            ]);

            $question->forceFill(['answer_post_id' => $answer->id])->save();
        }

        // A pinned, instructor-posted welcome thread.
        ForumThread::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Welcome — introduce yourself here'],
            [
                'user_id' => $instructor->id,
                'body' => '<p>Say hello and tell us what drew you to public relations. I\'ll keep this thread pinned all term.</p>',
                'is_pinned' => true,
                'last_activity_at' => now()->subHours(2),
                'created_at' => now()->subDays(3),
            ],
        );

        // A locked (archived) thread, to show the state.
        ForumThread::firstOrCreate(
            ['course_id' => $course->id, 'title' => 'Week 1 reading — now closed'],
            [
                'user_id' => $instructor->id,
                'body' => '<p>Thanks for the lively discussion. This thread is now locked; carry new questions into a fresh one.</p>',
                'is_locked' => true,
                'last_activity_at' => now()->subDays(5),
                'created_at' => now()->subDays(6),
            ],
        );
    }

    private function seedDirectConversation(Course $course, User $instructor, User $student): void
    {
        if ($student->is($instructor)) {
            return;
        }

        $exists = Conversation::query()
            ->where('type', ConversationType::Direct->value)
            ->whereHas('participants', fn ($q) => $q->whereKey($student->id))
            ->whereHas('participants', fn ($q) => $q->whereKey($instructor->id))
            ->exists();

        if ($exists) {
            return;
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Direct,
            'created_by' => $student->id,
            'last_message_at' => now()->subHours(4),
        ]);

        $conversation->participants()->attach([
            $student->id => ['last_read_at' => now()->subHours(4)],
            // Instructor hasn't opened it yet — leaves an unread for the demo.
            $instructor->id => ['last_read_at' => null],
        ]);

        $conversation->messages()->create([
            'user_id' => $student->id,
            'body' => '<p>Hello — could I get a quick extension on the reflection essay? I was unwell this week.</p>',
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $students
     */
    private function seedCourseGroup(Course $course, User $instructor, $students): void
    {
        if (Conversation::where('course_id', $course->id)->where('type', ConversationType::Group->value)->exists()) {
            return;
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Group,
            'subject' => $course->title.' — class announcements',
            'course_id' => $course->id,
            'created_by' => $instructor->id,
            'last_message_at' => now()->subHours(6),
        ]);

        $memberIds = $students->pluck('id')->push($instructor->id)->unique();
        $attach = $memberIds->mapWithKeys(fn ($id) => [
            $id => ['last_read_at' => $id === $instructor->id ? now()->subHours(6) : null],
        ])->all();
        $conversation->participants()->attach($attach);

        $conversation->messages()->create([
            'user_id' => $instructor->id,
            'body' => '<p>Welcome everyone. I\'ll use this group thread for quick reminders — this week\'s live session moves to Thursday 4pm.</p>',
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(6),
        ]);
    }
}
