<?php

namespace App\Enums;

/**
 * What a student may hand in for an assignment: an uploaded file, typed rich text,
 * or either/both. Submission validation reads these to decide which inputs are
 * required and which are rejected.
 */
enum AssignmentType: string
{
    case File = 'file';
    case Text = 'text';
    case Either = 'either';

    public function label(): string
    {
        return match ($this) {
            self::File => 'File upload',
            self::Text => 'Text entry',
            self::Either => 'Text or file',
        };
    }

    public function acceptsText(): bool
    {
        return $this !== self::File;
    }

    public function acceptsFiles(): bool
    {
        return $this !== self::Text;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
