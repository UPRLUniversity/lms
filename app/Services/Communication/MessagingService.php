<?php

namespace App\Services\Communication;

use App\Enums\ConversationType;
use App\Enums\EnrollmentStatus;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\Media\PrivateFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All messaging write logic + the unread-count maths. Conversations are gated purely by
 * participation (ConversationPolicy); WHO may open a conversation with WHOM is the rule
 * enforced here — a student may only reach people they share a course with, while staff
 * may reach any of their course members. The bell is fed by NewMessageNotification,
 * deduped so a burst of messages doesn't spam a recipient who hasn't caught up yet.
 */
class MessagingService
{
    /**
     * How many people the composer renders inline before it switches to server-side
     * search. Only ever reached by admins (everyone else's list is their coursemates).
     */
    private const CONTACT_LIST_CAP = 200;

    public function __construct(private readonly PrivateFileService $files) {}

    /**
     * Find the existing one-to-one conversation between two people, or start one. Direct
     * conversations are canonical: exactly one ever exists per pair, so replying always
     * lands in the same thread.
     */
    public function startDirect(User $actor, User $recipient): Conversation
    {
        $existing = Conversation::query()
            ->where('type', ConversationType::Direct->value)
            ->whereHas('participants', fn ($q) => $q->whereKey($actor->id))
            ->whereHas('participants', fn ($q) => $q->whereKey($recipient->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create([
            'type' => ConversationType::Direct,
            'created_by' => $actor->id,
            'last_message_at' => null,
        ]);

        $conversation->participants()->attach([$actor->id, $recipient->id]);

        return $conversation;
    }

    /**
     * Create a named group conversation. The actor is always a participant.
     *
     * @param  Collection<int, User>  $participants
     */
    public function createGroup(User $actor, string $subject, Collection $participants, ?Course $course = null): Conversation
    {
        $conversation = Conversation::create([
            'type' => ConversationType::Group,
            'subject' => $subject,
            'course_id' => $course?->id,
            'created_by' => $actor->id,
            'last_message_at' => null,
        ]);

        $ids = $participants->pluck('id')->push($actor->id)->unique()->all();
        $conversation->participants()->attach($ids);

        return $conversation;
    }

    /**
     * "Message all enrolled": one durable group thread per course. Reuses the course's
     * existing broadcast group if there is one (so it stays a single thread), tops up its
     * membership with anyone newly enrolled, and posts the message.
     */
    public function messageAllEnrolled(User $actor, Course $course, string $subject, string $body): Conversation
    {
        $conversation = Conversation::query()
            ->where('type', ConversationType::Group->value)
            ->where('course_id', $course->id)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'type' => ConversationType::Group,
                'subject' => $subject,
                'course_id' => $course->id,
                'created_by' => $actor->id,
                'last_message_at' => null,
            ]);
        }

        // Keep membership in step with the course community (idempotent).
        $memberIds = $course->members()->pluck('id')->push($actor->id)->unique()->all();
        $conversation->participants()->syncWithoutDetaching($memberIds);
        $conversation->load('participants');

        $this->sendMessage($actor, $conversation, $body);

        return $conversation;
    }

    /**
     * Post a message, optionally with a single private attachment. Marks the sender
     * caught-up, bumps the conversation's activity, and notifies the other participants
     * who were already caught up (dedupe: someone with unread already isn't re-pinged).
     */
    public function sendMessage(User $sender, Conversation $conversation, string $body, ?UploadedFile $attachment = null): Message
    {
        return DB::transaction(function () use ($sender, $conversation, $body, $attachment) {
            $conversation->loadMissing('participants');

            // Who is already caught up BEFORE this message lands (compute first).
            $caughtUp = $conversation->participants
                ->filter(fn (User $p) => $p->id !== $sender->id)
                ->filter(fn (User $p) => ! $this->hasUnread($conversation, $p))
                ->values();

            $message = $conversation->messages()->create([
                'user_id' => $sender->id,
                'body' => $body,
            ]);

            if ($attachment) {
                $this->files->store($attachment, MediaPurpose::MessageAttachments, $message);
            }

            $conversation->forceFill(['last_message_at' => now()])->save();

            // The sender has, by definition, read their own message.
            $conversation->participants()->updateExistingPivot($sender->id, ['last_read_at' => now()]);

            foreach ($caughtUp as $participant) {
                $participant->notify(new NewMessageNotification($message));
            }

            return $message;
        });
    }

    /**
     * Mark the whole conversation read for a user (drops their unread count to zero).
     */
    public function markRead(User $user, Conversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    /**
     * Per-conversation unread counts for a user, as [conversation_id => count]. One
     * grouped query joined to the read watermark — no per-row work on the inbox.
     *
     * @param  array<int, int>  $conversationIds
     * @return Collection<int, int>
     */
    public function unreadCounts(User $user, array $conversationIds): Collection
    {
        if ($conversationIds === []) {
            return collect();
        }

        return Message::query()
            ->join('conversation_user', function ($join) use ($user) {
                $join->on('conversation_user.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_user.user_id', '=', $user->id);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.user_id', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('conversation_user.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'messages.conversation_id');
    }

    /**
     * Total unread messages across all of a user's conversations — the number the
     * "Messages" nav badge shows.
     */
    public function totalUnread(User $user): int
    {
        return (int) Message::query()
            ->join('conversation_user', function ($join) use ($user) {
                $join->on('conversation_user.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_user.user_id', '=', $user->id);
            })
            ->where('messages.user_id', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('conversation_user.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at');
            })
            ->count();
    }

    /**
     * Whether $actor is allowed to open or continue a conversation with $recipient: staff may
     * reach any of their course members; everyone else must share a course. Enforced at
     * conversation creation (participation gates messages thereafter).
     */
    public function canMessage(User $actor, User $recipient): bool
    {
        if ($actor->is($recipient)) {
            return false;
        }

        if ($this->reachesEveryone($actor)) {
            return true;
        }

        $mine = $this->membershipCourseIds($actor);
        $theirs = $this->membershipCourseIds($recipient);

        return $mine->intersect($theirs)->isNotEmpty();
    }

    /**
     * The people a given user may start a direct conversation with. This MUST agree with
     * canMessage or the composer offers a list that the store endpoint then refuses (or,
     * as it did before this fix, offers nothing at all to an admin who canMessage says may
     * reach everyone — the composer showed "No one to message yet" on a permission that
     * was actually unlimited).
     *
     * Students and instructors get everyone they share a course with. Admins get the
     * whole active directory, capped: beyond the cap the picker searches server-side
     * rather than rendering thousands of <option>s (see searchContacts).
     *
     * @return Collection<int, User>
     */
    public function contactsFor(User $user): Collection
    {
        if ($this->reachesEveryone($user)) {
            return User::query()
                ->active()
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->limit(self::CONTACT_LIST_CAP)
                ->get();
        }

        $courseIds = $this->membershipCourseIds($user);

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return Course::query()
            ->whereIn('id', $courseIds)
            ->with('instructors')
            ->get()
            ->flatMap(fn (Course $course) => $course->members())
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $user->id)
            ->sortBy('name')
            ->values();
    }

    /**
     * Whether the viewer's contact list is capped — i.e. there are more people they may
     * message than contactsFor returned, so the composer must offer search.
     */
    public function contactsAreCapped(User $user): bool
    {
        if (! $this->reachesEveryone($user)) {
            return false;
        }

        return User::query()->active()->whereKeyNot($user->id)->count() > self::CONTACT_LIST_CAP;
    }

    /**
     * Type-ahead over the people the viewer may message. Runs the SAME reachability rule
     * as contactsFor — an admin searches the directory, everyone else searches only their
     * coursemates — so search can never surface someone store would then reject.
     *
     * @return Collection<int, User>
     */
    public function searchContacts(User $user, string $term, int $limit = 20): Collection
    {
        $term = trim($term);

        if ($this->reachesEveryone($user)) {
            return User::query()
                ->active()
                ->whereKeyNot($user->id)
                ->when($term !== '', fn ($q) => $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                }))
                ->orderBy('name')
                ->limit($limit)
                ->get();
        }

        return $this->contactsFor($user)
            ->when($term !== '', fn (Collection $c) => $c->filter(
                fn (User $u) => str_contains(mb_strtolower($u->name), mb_strtolower($term))
                    || str_contains(mb_strtolower($u->email), mb_strtolower($term))
            ))
            ->take($limit)
            ->values();
    }

    /**
     * Admins and super-admins may message anyone — the mirror of canMessage's admin
     * branch, kept as one private predicate so the two can never drift apart again.
     */
    private function reachesEveryone(User $user): bool
    {
        return $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }

    /**
     * Whether the participant has any unread messages right now (used to dedupe the
     * new-message notification).
     */
    private function hasUnread(Conversation $conversation, User $participant): bool
    {
        $pivot = $participant->pivot ?? $conversation->participants->firstWhere('id', $participant->id)?->pivot;
        $since = $pivot?->last_read_at;

        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $participant->id)
            ->when($since !== null, fn ($q) => $q->where('created_at', '>', $since))
            ->exists();
    }

    /**
     * Every course id a user is a member of — taught (instructor) or enrolled/completed
     * (student). The basis of "who may I message".
     *
     * @return Collection<int, int>
     */
    private function membershipCourseIds(User $user): Collection
    {
        $taught = Course::forInstructor($user)->pluck('id');

        $enrolled = $user->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->pluck('course_id');

        return $taught->concat($enrolled)->unique()->values();
    }
}
