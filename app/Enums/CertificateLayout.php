<?php

namespace App\Enums;

/**
 * The two shipped certificate designs. Each maps to its own PDF blade view (and the
 * same view doubles as the admin live-preview partial), so adding a third design later
 * is "add a case + a view", not a rewrite.
 */
enum CertificateLayout: string
{
    case Classic = 'classic';
    case Modern = 'modern';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Ivory background, gold rule borders.',
            self::Modern => 'White background, crimson diagonal motif.',
        };
    }

    /**
     * The blade view rendered for both the PDF (dompdf) and the admin HTML preview.
     */
    public function view(): string
    {
        return match ($this) {
            self::Classic => 'certificates.pdf.classic',
            self::Modern => 'certificates.pdf.modern',
        };
    }

    /**
     * The layout's own default accent colour, used unless a template overrides it.
     */
    public function defaultAccentColor(): string
    {
        return match ($this) {
            self::Classic => '#C9A227', // gold
            self::Modern => '#C8102E',  // crimson
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
