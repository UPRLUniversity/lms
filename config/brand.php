<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Institution identity
    |--------------------------------------------------------------------------
    */

    'name' => 'UPRL LMS',

    'university' => 'University of Public Relations and Leadership',

    'short' => 'UPRL',

    'motto' => 'Creativity, Competence, Character',

    /*
    |--------------------------------------------------------------------------
    | Logo files
    |--------------------------------------------------------------------------
    |
    | Paths are relative to the public/ directory. The <x-brand.logo> component
    | renders the file if it exists and falls back to an inline SVG monogram
    | otherwise — so dropping real artwork into public/images/brand/ requires
    | no code changes. See public/images/brand/README.md.
    |
    */

    'logos' => [
        'primary' => 'images/brand/logo.svg',       // full lockup, dark text — light backgrounds
        'mark' => 'images/brand/logo-mark.svg',      // standalone crest/sunburst mark
        'white' => 'images/brand/logo-white.svg',    // reversed lockup — crimson/dark backgrounds
    ],

];
