<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\Communication\MessagingService;
use Illuminate\Http\RedirectResponse;

/**
 * Posting a message into an existing conversation. Authorization (participant-only) and
 * validation live in StoreMessageRequest; the send logic — attachment storage, activity
 * bump, read watermark, deduped notification — lives in MessagingService.
 */
class MessageController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->messaging->sendMessage(
            $request->user(),
            $conversation,
            (string) $request->validated('body'),
            $request->file('attachment'),
        );

        return redirect()->route('messages.show', $conversation);
    }
}
