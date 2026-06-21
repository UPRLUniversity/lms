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

        MediaPurpose::Submissions->value => [
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => [
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'text/plain',
            ],
            'max_kb' => 20480,
            'transformations' => [],
        ],

        MediaPurpose::Certificates->value => [
            'visibility' => 'private',
            'disk' => 'private',
            'allowed_mimes' => ['application/pdf'],
            'max_kb' => 10240,
            'transformations' => [],
        ],
    ],

];
