<?php

namespace App\Enums;

/**
 * The two shapes a conversation takes. String-backed so the value is exactly what's
 * stored in conversations.type.
 *
 *   direct → a one-to-one thread between two people (a student and their instructor,
 *            or two classmates); reused if it already exists.
 *   group  → a named, multi-party thread (e.g. an instructor messaging every enrolled
 *            student of a course at once). Only instructors/admins can create these.
 */
enum ConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';

    public function isDirect(): bool
    {
        return $this === self::Direct;
    }

    public function isGroup(): bool
    {
        return $this === self::Group;
    }
}
