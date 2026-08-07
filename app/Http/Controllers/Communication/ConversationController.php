<?php

namespace App\Http\Controllers\Communication;

use App\Enums\ConversationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\MessageAllEnrolledRequest;
use App\Http\Requests\Communication\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\User;
use App\Services\Communication\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The messaging inbox (/messages): the conversation list (unread-bold + counts + last
 * preview), the thread pane, the composer, and the two "start a conversation" entry
 * points — a direct message to one person and an instructor's "message all enrolled"
 * course group. Participation gates every read/write (ConversationPolicy); who may
 * reach whom is enforced through MessagingService::canMessage.
 */
class ConversationController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    /**
     * The inbox with no thread open yet — the list plus a "pick a conversation" prompt.
     */
    public function index(Request $request): View
    {
        $this->authorizeMessaging($request);

        return view('messages.index', [
            'conversations' => $this->conversationList($request->user()),
            'active' => null,
        ]);
    }

    /**
     * The composer for a brand-new conversation.
     */
    public function create(Request $request): View
    {
        $this->authorizeMessaging($request);

        $user = $request->user();

        return view('messages.create', [
            'conversations' => $this->conversationList($user),
            'contacts' => $this->messaging->contactsFor($user),
            'contactsAreCapped' => $this->messaging->contactsAreCapped($user),
            'canCreateGroup' => $request->user()->can('createGroupConversation'),
        ]);
    }

    /**
     * Type-ahead for the composer's recipient picker. Only reachable people are returned
     * (MessagingService::searchContacts runs the same rule as canMessage), so this cannot
     * be used as a user-directory scrape by someone whose reach is their coursemates.
     */
    public function contacts(Request $request): JsonResponse
    {
        $this->authorizeMessaging($request);

        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $matches = $this->messaging->searchContacts(
            $request->user(),
            (string) $request->query('q', ''),
        );

        return response()->json([
            'results' => $matches->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values(),
        ]);
    }

    /**
     * Start a direct or group conversation and post its first message.
     */
    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($data['type'] === ConversationType::Direct->value) {
            $recipient = User::findOrFail($data['recipient_id']);

            if (! $this->messaging->canMessage($user, $recipient)) {
                throw ValidationException::withMessages([
                    'recipient_id' => 'You can only message people you share a course with.',
                ]);
            }

            $conversation = $this->messaging->startDirect($user, $recipient);
            $this->messaging->sendMessage($user, $conversation, $data['body']);

            return redirect()->route('messages.show', $conversation);
        }

        // Group — staff only (the request's authorize() already enforced the capability).
        $participants = User::whereIn('id', $data['participant_ids'])->get()
            ->filter(fn (User $u) => $this->messaging->canMessage($user, $u))
            ->values();

        if ($participants->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Choose at least one person you share a course with.',
            ]);
        }

        $conversation = $this->messaging->createGroup($user, $data['subject'], $participants);
        $this->messaging->sendMessage($user, $conversation, $data['body']);

        return redirect()->route('messages.show', $conversation)
            ->with('status', 'Group conversation started.');
    }

    /**
     * Open a conversation — marks it read for the viewer, shows its messages, and keeps
     * the inbox list alongside (two-pane).
     */
    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $user = $request->user();
        $this->messaging->markRead($user, $conversation);

        $conversation->load([
            'participants',
            'course',
            'messages.sender',
            'messages.media',
        ]);

        return view('messages.index', [
            'conversations' => $this->conversationList($user),
            'active' => $conversation,
        ]);
    }

    /**
     * "Message" button on a person / course page: open (or reuse) a direct conversation
     * and land in it, ready to type.
     */
    public function startWith(Request $request, User $user): RedirectResponse
    {
        $this->authorizeMessaging($request);

        $actor = $request->user();

        abort_unless($this->messaging->canMessage($actor, $user), 403);

        $conversation = $this->messaging->startDirect($actor, $user);

        return redirect()->route('messages.show', $conversation);
    }

    /**
     * "Message all enrolled" — an instructor broadcasts to the course's single group
     * thread (created on first use, reused thereafter).
     */
    public function messageCourse(MessageAllEnrolledRequest $request, Course $course): RedirectResponse
    {
        $conversation = $this->messaging->messageAllEnrolled(
            $request->user(),
            $course,
            $request->validated('subject'),
            $request->validated('body'),
        );

        return redirect()->route('messages.show', $conversation)
            ->with('status', 'Message sent to everyone enrolled.');
    }

    /**
     * Build the viewer's conversation list with a per-thread unread count attached.
     *
     * @return Collection<int, Conversation>
     */
    private function conversationList(User $user): Collection
    {
        $conversations = $user->conversations()
            ->with(['participants', 'latestMessage.sender', 'course'])
            ->orderByRaw('COALESCE(last_message_at, conversations.created_at) DESC')
            ->get();

        $unread = $this->messaging->unreadCounts($user, $conversations->pluck('id')->all());

        return $conversations->each(function (Conversation $conversation) use ($unread) {
            $conversation->unread_count = (int) ($unread[$conversation->id] ?? 0);
        });
    }

    /**
     * Messaging is closed to the read-only auditor; everyone else may use it.
     */
    private function authorizeMessaging(Request $request): void
    {
        abort_unless($request->user()->can('useMessaging'), 403);
    }
}
