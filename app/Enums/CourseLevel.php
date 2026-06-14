<?php

namespace App\Enums;

/**
 * Academic level a course is pitched at. Mirrors UPRL's programme structure.
 */
enum CourseLevel: string
{
    case Certificate = 'certificate';
    case Undergraduate = 'undergraduate';
    case Postgraduate = 'postgraduate';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Certificate => 'Certificate',
            self::Undergraduate => 'Undergraduate',
            self::Postgraduate => 'Postgraduate',
            self::Professional => 'Professional',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $l) => $l->value, self::cases());
    }
}
