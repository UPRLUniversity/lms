<?php

namespace App\Enums;

/**
 * Whether a programme's parts must be worked through in order.
 *
 * Deliberately two values and not a spectrum. "Sequential" already carries the whole
 * rule — every earlier part cleared — and the two bars that define "cleared" are
 * configured on the parts themselves, not here.
 */
enum ProgressionRule: string
{
    case Open = 'open';
    case Sequential = 'sequential';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open — any part, any order',
            self::Sequential => 'Sequential — each part unlocks the next',
        };
    }

    /**
     * Plain-language help for the programme form, so an admin switching this on knows
     * exactly what it will do to students before they save.
     */
    public function help(): string
    {
        return match ($this) {
            self::Open => 'Students may enrol in any course of this programme at any time. This is the default.',
            self::Sequential => 'A student must pass every compulsory course of the earlier parts (and reach their credit target, where one is set) before enrolling in a later part. The first part is always open, and students already enrolled are never removed.',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'neutral',
            self::Sequential => 'gold',
        };
    }

    public function isSequential(): bool
    {
        return $this === self::Sequential;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
