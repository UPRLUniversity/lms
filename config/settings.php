<?php

use App\Enums\EnrollmentMode;
use App\Enums\ReviewPolicy;
use App\Enums\SettingGroup;

/*
|--------------------------------------------------------------------------
| Runtime settings — the schema
|--------------------------------------------------------------------------
|
| The authoritative definition of every value an administrator may change from
| /admin/settings. The `settings` table stores only what has actually been
| CHANGED; everything else falls back to the `default` declared here, so a fresh
| install behaves identically to one that has never opened the screen.
|
| Each entry:
|
|   group    SettingGroup — which tab it appears under
|   type     string|text|email|bool|int|select|media|timezone — drives both the
|            form control and the cast applied on read (SettingsRepository)
|   default  value used when the row is absent. `null` with a `config` pointer
|            means "inherit whatever the config file says"
|   config   OPTIONAL dotted config key this setting OVERRIDES at boot
|            (SettingsServiceProvider). This is what makes a settings change
|            reach code that already reads config() — the app chrome, the mail
|            templates, the certificate renderer and the public site all read
|            config('brand.*'), so overriding it there propagates everywhere
|            without a single call site changing.
|   rules    validation applied on save, on top of the type
|   options  for `type => select`: value => label, or a callable resolving to one
|   label    field label
|   help     one line under the field
|   secret   true → value is redacted from audit-log diffs
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Settings are read on essentially every request, so the resolved map is
    | cached indefinitely and busted on write (SettingsRepository::flush).
    */

    'cache_key' => 'uprl:settings',

    /*
    |--------------------------------------------------------------------------
    | Locales offered by the switcher
    |--------------------------------------------------------------------------
    | Adding a language = add a lang/<code> directory and a line here. See
    | docs/localization.md. The switcher itself only appears when
    | general.locale_switcher_enabled is on.
    */

    'locales' => [
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Date formats offered
    |--------------------------------------------------------------------------
    */

    'date_formats' => [
        'd M Y' => '05 Aug 2026',
        'd/m/Y' => '05/08/2026',
        'm/d/Y' => '08/05/2026',
        'Y-m-d' => '2026-08-05',
        'j F Y' => '5 August 2026',
    ],

    /*
    |--------------------------------------------------------------------------
    | The settings themselves
    |--------------------------------------------------------------------------
    */

    'definitions' => [

        /*
        |----------------------------------------------------------------------
        | General
        |----------------------------------------------------------------------
        */

        'general.university_name' => [
            'group' => SettingGroup::General,
            'type' => 'string',
            'default' => null,
            'config' => 'brand.university',
            'label' => 'University name',
            'help' => 'The full legal name, used in e-mail footers, certificates and page metadata.',
            'rules' => ['required', 'string', 'max:150'],
        ],

        'general.app_name' => [
            'group' => SettingGroup::General,
            'type' => 'string',
            'default' => null,
            'config' => 'brand.name',
            'label' => 'Application name',
            'help' => 'The short product name shown in the browser tab and the sidebar.',
            'rules' => ['required', 'string', 'max:60'],
        ],

        'general.short_name' => [
            'group' => SettingGroup::General,
            'type' => 'string',
            'default' => null,
            'config' => 'brand.short',
            'label' => 'Abbreviation',
            'help' => 'Used where space is tight — the logo fallback monogram, compact headings.',
            'rules' => ['required', 'string', 'max:12'],
        ],

        'general.motto' => [
            'group' => SettingGroup::General,
            'type' => 'string',
            'default' => null,
            'config' => 'brand.motto',
            'label' => 'Motto',
            'help' => 'Signs off every e-mail and sits under the sidebar logo.',
            'rules' => ['required', 'string', 'max:120'],
        ],

        'general.support_email' => [
            'group' => SettingGroup::General,
            'type' => 'email',
            'default' => null,
            'config' => 'mail.support',
            'label' => 'Support e-mail',
            'help' => 'Where learners are told to write when something goes wrong.',
            'rules' => ['required', 'email', 'max:150'],
        ],

        'general.timezone' => [
            'group' => SettingGroup::General,
            'type' => 'timezone',
            'default' => 'Africa/Lagos',
            'config' => 'app.timezone',
            'label' => 'Timezone',
            'help' => 'Governs every displayed date and the deadline a submission is judged against.',
            'rules' => ['required', 'timezone'],
        ],

        'general.date_format' => [
            'group' => SettingGroup::General,
            'type' => 'select',
            'default' => 'd M Y',
            'label' => 'Date format',
            'help' => 'How dates render across the app. Times are always 24-hour.',
            'options' => 'date_formats',
            'rules' => ['required', 'string'],
        ],

        'general.locale' => [
            'group' => SettingGroup::General,
            'type' => 'select',
            'default' => 'en',
            'config' => 'app.locale',
            'label' => 'Default language',
            'help' => 'The language a visitor gets before choosing one of their own.',
            'options' => 'locales',
            'rules' => ['required', 'string', 'max:10'],
        ],

        'general.locale_switcher_enabled' => [
            'group' => SettingGroup::General,
            'type' => 'bool',
            'default' => false,
            'label' => 'Show the language switcher',
            'help' => 'Off until a second language exists — a switcher with one option is noise. See docs/localization.md.',
        ],

        /*
        |----------------------------------------------------------------------
        | Branding
        |----------------------------------------------------------------------
        | The three logo variants and the favicon are stored as Media ids and
        | resolved by App\Services\Branding\BrandAssets, which every consumer
        | (chrome, mail header, certificate PDF, report PDF, public site) goes
        | through — so replacing artwork here needs no code change.
        */

        'branding.logo_color' => [
            'group' => SettingGroup::Branding,
            'type' => 'media',
            'default' => null,
            'label' => 'Primary logo',
            'help' => 'Full lockup with dark text, for light backgrounds. PNG or SVG, ideally 2× the display size.',
            'purpose' => 'brand_assets',
        ],

        'branding.logo_white' => [
            'group' => SettingGroup::Branding,
            'type' => 'media',
            'default' => null,
            'label' => 'Reversed logo',
            'help' => 'Knockout lockup for crimson and dark backgrounds — the sidebar and the e-mail header.',
            'purpose' => 'brand_assets',
        ],

        'branding.logo_mark' => [
            'group' => SettingGroup::Branding,
            'type' => 'media',
            'default' => null,
            'label' => 'Mark only',
            'help' => 'The crest without the wordmark, for the collapsed sidebar.',
            'purpose' => 'brand_assets',
        ],

        'branding.favicon' => [
            'group' => SettingGroup::Branding,
            'type' => 'media',
            'default' => null,
            'label' => 'Favicon',
            'help' => 'The browser-tab icon. A square PNG of at least 32×32.',
            'purpose' => 'brand_assets',
        ],

        'branding.login_tagline' => [
            'group' => SettingGroup::Branding,
            'type' => 'string',
            'default' => 'Creativity, Competence, Character — study for your professional qualification.',
            'label' => 'Sign-in panel tagline',
            'help' => 'The welcoming line beside the sign-in form.',
            'rules' => ['nullable', 'string', 'max:180'],
        ],

        /*
        |----------------------------------------------------------------------
        | Enrolment defaults
        |----------------------------------------------------------------------
        */

        'enrollment.default_mode' => [
            'group' => SettingGroup::Enrollment,
            'type' => 'select',
            'default' => EnrollmentMode::Open->value,
            'label' => 'Default enrolment mode',
            'help' => 'What a newly created course starts as. The course builder can always change it.',
            'options' => [
                EnrollmentMode::Open->value => 'Open — learners enrol themselves',
                EnrollmentMode::Approval->value => 'Approval — a request an instructor accepts',
                EnrollmentMode::InviteOnly->value => 'Invite only — staff enrol on the learner\'s behalf',
            ],
            'rules' => ['required', 'string'],
        ],

        'enrollment.default_capacity' => [
            'group' => SettingGroup::Enrollment,
            'type' => 'int',
            'default' => null,
            'label' => 'Default capacity',
            'help' => 'Seats a new course offers. Leave blank for unlimited.',
            'rules' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ],

        'enrollment.allow_self_withdraw' => [
            'group' => SettingGroup::Enrollment,
            'type' => 'bool',
            'default' => true,
            'label' => 'Learners may withdraw themselves',
            'help' => 'When off, only staff can remove someone from a course.',
        ],

        /*
        |----------------------------------------------------------------------
        | Assessment defaults
        |----------------------------------------------------------------------
        */

        'assessment.default_review_policy' => [
            'group' => SettingGroup::Assessment,
            'type' => 'select',
            'default' => ReviewPolicy::AfterClose->value,
            'label' => 'Default review policy',
            'help' => 'When a learner may see their answers against the correct ones.',
            'options' => [
                ReviewPolicy::Immediately->value => 'Immediately after submitting',
                ReviewPolicy::AfterClose->value => 'Once the assessment closes',
                ReviewPolicy::Never->value => 'Never',
            ],
            'rules' => ['required', 'string'],
        ],

        'assessment.default_max_attempts' => [
            'group' => SettingGroup::Assessment,
            'type' => 'int',
            'default' => 1,
            'label' => 'Default attempts allowed',
            'help' => 'How many times a learner may sit a new assessment.',
            'rules' => ['required', 'integer', 'min:1', 'max:20'],
        ],

        'assessment.default_pass_mark' => [
            'group' => SettingGroup::Assessment,
            'type' => 'int',
            'default' => 50,
            'label' => 'Default pass mark (%)',
            'help' => 'The score a new assessment requires to count as passed.',
            'rules' => ['required', 'integer', 'min:0', 'max:100'],
        ],

        /*
        |----------------------------------------------------------------------
        | Grading
        |----------------------------------------------------------------------
        | The selector writes GradeScale.is_default rather than storing an id of
        | its own — the Section 6.5 admin screen stays the single writer of scale
        | state, and Course::gradeScaleOrDefault() already reads it.
        */

        'grading.default_scale_id' => [
            'group' => SettingGroup::Grading,
            'type' => 'select',
            'default' => null,
            'label' => 'System default grade scale',
            'help' => 'Governs every course that has not chosen a scale of its own.',
            'options' => 'grade_scales',
            'rules' => ['nullable', 'integer'],
            'derived' => true,
        ],

        /*
        |----------------------------------------------------------------------
        | Commerce
        |----------------------------------------------------------------------
        */

        'commerce.currency' => [
            'group' => SettingGroup::Commerce,
            'type' => 'string',
            'default' => null,
            'config' => 'commerce.currency',
            'label' => 'Currency code',
            'help' => 'The ISO code handed to payment gateways, e.g. NGN.',
            'rules' => ['required', 'string', 'size:3', 'alpha'],
        ],

        'commerce.currency_symbol' => [
            'group' => SettingGroup::Commerce,
            'type' => 'string',
            'default' => null,
            'config' => 'commerce.symbol',
            'label' => 'Currency symbol',
            'help' => 'What buyers actually see beside a price.',
            'rules' => ['required', 'string', 'max:5'],
        ],

        'commerce.money_locale' => [
            'group' => SettingGroup::Commerce,
            'type' => 'string',
            'default' => 'en_NG',
            'config' => 'commerce.money_locale',
            'label' => 'Money formatting locale',
            'help' => 'Decides thousands separators and decimal marks, e.g. en_NG → ₦12,500.00.',
            'rules' => ['required', 'string', 'max:12'],
        ],

        'commerce.invoice_footer' => [
            'group' => SettingGroup::Commerce,
            'type' => 'text',
            'default' => 'Thank you for studying with us. This receipt confirms payment in full.',
            'config' => 'commerce.invoice_footer',
            'label' => 'Invoice / receipt footer',
            'help' => 'Printed at the foot of every receipt — VAT registration, remittance notes, thanks.',
            'rules' => ['nullable', 'string', 'max:500'],
        ],

        'commerce.guest_checkout_enabled' => [
            'group' => SettingGroup::Commerce,
            'type' => 'bool',
            'default' => true,
            'config' => 'commerce.guest_checkout_enabled',
            'label' => 'Allow browsing and building a cart signed out',
            'help' => 'An account is always required to complete payment; this governs whether a stranger may fill a basket first.',
        ],

        /*
        |----------------------------------------------------------------------
        | Uploads
        |----------------------------------------------------------------------
        | Ceilings applied ON TOP of each MediaPurpose's own allow-list in
        | config/media.php — the stricter of the two always wins, so lowering a
        | limit here can never widen what a purpose accepts.
        */

        'uploads.max_image_kb' => [
            'group' => SettingGroup::Uploads,
            'type' => 'int',
            'default' => 4096,
            'config' => 'media.limits.image_kb',
            'label' => 'Maximum image size (KB)',
            'help' => 'Applies to avatars, covers and in-editor images.',
            'rules' => ['required', 'integer', 'min:128', 'max:51200'],
        ],

        'uploads.max_document_kb' => [
            'group' => SettingGroup::Uploads,
            'type' => 'int',
            'default' => 20480,
            'config' => 'media.limits.document_kb',
            'label' => 'Maximum document size (KB)',
            'help' => 'Applies to resources, submissions and message attachments.',
            'rules' => ['required', 'integer', 'min:256', 'max:102400'],
        ],

        'uploads.allowed_image_mimes' => [
            'group' => SettingGroup::Uploads,
            'type' => 'string',
            'default' => 'image/jpeg,image/png,image/webp,image/gif',
            'config' => 'media.limits.image_mimes',
            'label' => 'Allowed image types',
            'help' => 'Comma-separated MIME types. Narrowing this narrows every image upload path at once.',
            'rules' => ['required', 'string', 'max:400'],
        ],

        'uploads.allowed_document_mimes' => [
            'group' => SettingGroup::Uploads,
            'type' => 'string',
            'default' => 'application/pdf,application/msword,'
                .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.openxmlformats-officedocument.presentationml.presentation,'
                .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                .'application/zip,text/plain,'
                .'image/jpeg,image/png,image/webp',
            'config' => 'media.limits.document_mimes',
            'label' => 'Allowed document types',
            'help' => 'Comma-separated MIME types accepted anywhere a document is uploaded. '
                .'Images belong here too: a photographed hand-in previews inline when graded.',
            'rules' => ['required', 'string', 'max:800'],
        ],

        /*
        |----------------------------------------------------------------------
        | Security
        |----------------------------------------------------------------------
        */

        'security.password_min_length' => [
            'group' => SettingGroup::Security,
            'type' => 'int',
            'default' => 8,
            'config' => 'auth.password_min_length',
            'label' => 'Minimum password length',
            'help' => 'Enforced on registration, invitation acceptance and password reset.',
            'rules' => ['required', 'integer', 'min:8', 'max:64'],
        ],

        'security.session_lifetime' => [
            'group' => SettingGroup::Security,
            'type' => 'int',
            'default' => null,
            'config' => 'session.lifetime',
            'label' => 'Session lifetime (minutes)',
            'help' => 'How long an idle sign-in survives before it must be repeated.',
            'rules' => ['required', 'integer', 'min:5', 'max:20160'],
        ],

        'security.force_email_verification' => [
            'group' => SettingGroup::Security,
            'type' => 'bool',
            'default' => true,
            'config' => 'auth.force_email_verification',
            'label' => 'Require e-mail verification',
            'help' => 'When off, a new account reaches the dashboard before confirming its address. Checkout never required it either way.',
        ],
    ],
];
