<?php

namespace App\Enums;

/**
 * The tabs on /admin/settings. A group is purely presentational grouping — the
 * authoritative list of settings, their types and their defaults lives in
 * config/settings.php, keyed by "<group>.<name>".
 */
enum SettingGroup: string
{
    case General = 'general';
    case Branding = 'branding';
    case Enrollment = 'enrollment';
    case Assessment = 'assessment';
    case Grading = 'grading';
    case Commerce = 'commerce';
    case Uploads = 'uploads';
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Branding => 'Branding',
            self::Enrollment => 'Enrolment',
            self::Assessment => 'Assessment',
            self::Grading => 'Grading',
            self::Commerce => 'Commerce',
            self::Uploads => 'Uploads',
            self::Security => 'Security',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::General => 'cog',
            self::Branding => 'sparkles',
            self::Enrollment => 'user-plus',
            self::Assessment => 'clipboard-check',
            self::Grading => 'list',
            self::Commerce => 'receipt',
            self::Uploads => 'document',
            self::Security => 'shield',
        };
    }

    /**
     * One line of orientation under the tab heading — what this group governs and,
     * where it matters, what it deliberately does NOT govern.
     */
    public function description(): string
    {
        return match ($this) {
            self::General => 'Who the institution is, and how dates and times are rendered across the app.',
            self::Branding => 'Artwork and copy that identify the university. Changes here reach the app chrome, e-mails, certificates and the public site.',
            self::Enrollment => 'The defaults a new course starts with. An individual course may always override them.',
            self::Assessment => 'The defaults a new assessment starts with. An individual assessment may always override them.',
            self::Grading => 'Which scale governs a course that has not chosen one of its own.',
            self::Commerce => 'How money is displayed and what a buyer sees on a receipt. Gateway credentials live on the Payment methods screen, not here.',
            self::Uploads => 'Server-side ceilings applied to every upload path, on top of each purpose\'s own allow-list.',
            self::Security => 'Password strength, session length and whether a new account must confirm its e-mail.',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}
