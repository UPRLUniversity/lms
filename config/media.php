<?php

use App\Enums\MediaPurpose;

return [

    /*
    |--------------------------------------------------------------------------
    | Public-image driver
    |--------------------------------------------------------------------------
    |
    | Backend for PUBLIC images handled by MediaUploadService. "local" writes to
    | the public disk and fabricates a local URL (used in dev + the test suite,
    | so no Cloudinary account or network is needed). "cloudinary" uploads to
    | Cloudinary with the per-purpose transformations below.
    |
    | Supported: "local", "cloudinary"
    |
    */

    'driver' => env('MEDIA_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Cloudinary root folder
    |--------------------------------------------------------------------------
    |
    | Every public image is filed under "<root>/<purpose>" — uprl/avatars,
    | uprl/course_covers and so on — so nothing lands loose at the account root
    | and a new MediaPurpose gets its own folder without a decision.
    |
    | Configurable so a staging deployment can point at the SAME Cloudinary
    | account under its own root (UPRL_LMS_staging) without test uploads
    | landing in the live library.
    |
    */

    'root_folder' => env('CLOUDINARY_ROOT_FOLDER', 'uprl'),

    /*
    |--------------------------------------------------------------------------
    | Purposes
    |--------------------------------------------------------------------------
    |
    | The single source of truth mapping each MediaPurpose to its storage policy.
    | Change a purpose's disk/visibility here without touching application code.
    |
    |   visibility     public | private
    |   disk           filesystem disk (public images: "public"; private: "private")
    |   allowed_mimes  server-side allow-list (validated inside the services)
    |   max_kb         maximum upload size in kilobytes
    |   transformations Cloudinary hints applied to public images on upload
    |
    */

    'purposes' => [

        MediaPurpose::Avatars->value => [
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => 2048,
            'transformations' => ['width' => 256, 'height' => 256, 'crop' => 'fill', 'gravity' => 'face'],
        ],

        MediaPurpose::CourseCovers->value => [
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => 4096,
            'transformations' => ['width' => 1200, 'height' => 630, 'crop' => 'fill'],
        ],

        MediaPurpose::ProgrammeCovers->value => [
            // The hero image on a programme card and its landing page. Wider crop than a
            // course cover because it is displayed as a banner, not a 3:2 card thumbnail.
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => 4096,
            'transformations' => ['width' => 1600, 'height' => 600, 'crop' => 'fill'],
        ],

        MediaPurpose::LessonImages->value => [
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max_kb' => 4096,
            'transformations' => ['width' => 1600, 'crop' => 'limit'],
        ],

        MediaPurpose::EditorUploads->value => [
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max_kb' => 4096,
            'transformations' => ['width' => 1600, 'crop' => 'limit'],
        ],

        MediaPurpose::QuestionImages->value => [
            // An optional diagram/figure attached to a question prompt — a public image,
            // served via the CDN like a lesson image.
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max_kb' => 4096,
            'transformations' => ['width' => 1200, 'crop' => 'limit'],
        ],

        MediaPurpose::Signatures->value => [
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => ['image/png', 'image/webp'],
            'max_kb' => 1024,
            'transformations' => ['width' => 600, 'crop' => 'limit'],
        ],

        MediaPurpose::LessonMedia->value => [
            // The primary uploaded file for a file-type lesson (PDF/document/audio)
            // or the exceptional self-hosted lesson video. Private: streamed only
            // through a policy-gated/signed route, never a public CDN URL. The size
            // ceiling is configurable so the human can raise it for video without a
            // code change.
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'audio/mpeg',
                'audio/mp4',
                'audio/wav',
                'audio/x-wav',
                'audio/ogg',
                'audio/webm',
                'video/mp4',
                'video/webm',
                'video/quicktime',
            ],
            'max_kb' => (int) env('LESSON_MEDIA_MAX_KB', 25600),
            'transformations' => [],
        ],

        MediaPurpose::LessonResources->value => [
            // Course materials for enrolled users — gated, not on a public CDN.
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
            ],
            'max_kb' => 20480,
            'transformations' => [],
        ],

        MediaPurpose::AssignmentResources->value => [
            // Instructor-attached briefs/templates on an assignment. Private like lesson
            // resources: enrolled students download via the policy-gated route.
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'image/jpeg',
                'image/png',
            ],
            'max_kb' => 20480,
            'transformations' => [],
        ],

        MediaPurpose::Submissions->value => [
            // Images are accepted alongside documents so the grading workspace can
            // preview them inline (pdf + images render in place; the rest download).
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_kb' => 20480,
            'transformations' => [],
        ],

        MediaPurpose::MessageAttachments->value => [
            // A single file a user attaches to a direct/group message. Private: reachable
            // only through the policy-gated download route by a conversation participant,
            // never a public CDN URL. A modest ceiling keeps messaging light.
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'text/plain',
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_kb' => 10240,
            'transformations' => [],
        ],

        MediaPurpose::Certificates->value => [
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => ['application/pdf'],
            'max_kb' => 10240,
            'transformations' => [],
        ],

        MediaPurpose::BrandAssets->value => [
            // Logos and the favicon, uploaded from /admin/settings. SVG is accepted
            // because a logo is exactly the case vector belongs to, and ICO because
            // that is still what a favicon is often supplied as. No transformation:
            // brand artwork arrives at the size it was drawn for, and re-cropping a
            // lockup is how you get a logo with its wordmark cut off.
            'visibility' => 'public',
            'disk' => 'public',
            'allowed_mimes' => [
                'image/png',
                'image/jpeg',
                'image/webp',
                'image/svg+xml',
                'image/x-icon',
                'image/vnd.microsoft.icon',
            ],
            'max_kb' => 2048,
            'transformations' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Administrator-set ceilings (Section 15)
    |--------------------------------------------------------------------------
    |
    | Overridden at boot from /admin/settings → Uploads, and applied ON TOP of the
    | per-purpose policy above: MediaPurpose takes the SMALLER max size and the
    | INTERSECTION of the mime lists. Tightening here can therefore never widen
    | what a purpose accepts — only narrow it.
    |
    | Which purposes each ceiling governs is listed EXPLICITLY rather than inferred
    | from mime prefixes, because inference gets the interesting cases wrong. Three
    | purposes are deliberately left out:
    |
    |   LessonMedia   its ceiling is intentionally large and env-tunable for the
    |                 exceptional self-hosted video; a general document limit must
    |                 not quietly halve it
    |   BrandAssets   needs SVG and ICO, which have no business in the general
    |                 image allow-list
    |   Signatures    a deliberately narrow PNG/WebP-only case, already stricter
    |
    | Certificates is absent because nothing uploads to it — the PDFs are generated.
    |
    */

    'limits' => [

        'image_kb' => 4096,
        'image_mimes' => 'image/jpeg,image/png,image/webp,image/gif',
        'image_purposes' => [
            MediaPurpose::Avatars->value,
            MediaPurpose::CourseCovers->value,
            MediaPurpose::ProgrammeCovers->value,
            MediaPurpose::LessonImages->value,
            MediaPurpose::QuestionImages->value,
            MediaPurpose::EditorUploads->value,
        ],

        'document_kb' => 20480,
        // The UNION of what the governed purposes legitimately accept — images
        // included, because submissions, assignment resources and message attachments
        // all take them on purpose (a photographed hand-in previews inline in the
        // grading workspace). Since MediaPurpose INTERSECTS this with each purpose's
        // own list, a type listed here is only ever accepted where that purpose already
        // allowed it: lesson resources still refuse images. Listing a narrower set here
        // would silently revoke a deliberate Section 6 decision.
        'document_mimes' => 'application/pdf,application/msword,'
            .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
            .'application/vnd.openxmlformats-officedocument.presentationml.presentation,'
            .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
            .'application/zip,text/plain,'
            .'image/jpeg,image/png,image/webp',
        'document_purposes' => [
            MediaPurpose::LessonResources->value,
            MediaPurpose::AssignmentResources->value,
            MediaPurpose::Submissions->value,
            MediaPurpose::MessageAttachments->value,
        ],
    ],

];
