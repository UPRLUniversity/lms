<?php

/*
|--------------------------------------------------------------------------
| HTMLPurifier (mews/purifier) — allow-list sanitization
|--------------------------------------------------------------------------
|
| MANDATORY server-side sanitization for ALL rich-text fields. The allow-lists
| below mirror the TinyMCE valid_elements in resources/js/rich-editor.js. The
| RichHtml cast applies a profile on save, so a field is sanitized just by casting.
|
|   rich  — full academic content (lessons, assignments, announcements)
|   basic — high-risk user input (forum posts, messages): no images/tables
|
| HTMLPurifier strips <script>, on* handlers, and javascript: URLs by default
| (URI.AllowedSchemes), and removes any tag/attribute not explicitly listed.
|
*/

$common = [
    'HTML.Doctype' => 'HTML 4.01 Transitional',
    'HTML.TargetBlank' => true,            // external links open safely (adds rel)
    'Attr.AllowedRel' => ['noopener', 'noreferrer', 'nofollow'],
    'AutoFormat.RemoveEmpty' => true,
    'Core.RemoveInvalidImg' => true,
    'Cache.DefinitionImpl' => null,        // no on-disk definition cache needed
    'URI.AllowedSchemes' => [
        'http' => true,
        'https' => true,
        'mailto' => true,
    ],
];

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [
        'default' => array_merge($common, [
            'HTML.Allowed' => 'p,br,strong,b,em,i,u,a[href|title]',
        ]),

        'rich' => array_merge($common, [
            'HTML.Allowed' => implode(',', [
                'p', 'br', 'hr',
                'h2', 'h3', 'h4',
                'strong', 'b', 'em', 'i', 'u', 's',
                'ul', 'ol', 'li',
                'blockquote', 'pre', 'code',
                'a[href|title|target|rel]',
                'img[src|alt|width|height]',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
            ]),
        ]),

        'basic' => array_merge($common, [
            // Forums / messages — text formatting only, no images or tables.
            'HTML.Allowed' => implode(',', [
                'p', 'br',
                'strong', 'b', 'em', 'i', 'u',
                'ul', 'ol', 'li',
                'blockquote', 'code',
                'a[href|title]',
            ]),
        ]),
    ],
];
