<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\User;

/**
 * Participation is the gate: only someone who is a participant of a conversation may
 * read it or post to it. Adding people to a group is the group creator's or an admin's
 * call. The super-admin short-circuits via the Gate::before hook.
 */
class ConversationPolicy
{
    /**
     * Read a conversation and its messages — participants only.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Post a message — participants only.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Add more people — a group conversation, changed by its creator or an admin.
     */
    public function addParticipants(User $user, Conversation $conversation): bool
    {
        if (! $conversation->isGroup()) {
            return false;
        }

        return $conversation->created_by === $user->id
            || $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }
}
