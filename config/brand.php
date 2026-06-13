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
        'color' => 'images/brand/uprl-logo-color.png',   // full lockup, dark text — LIGHT backgrounds
        'white' => 'images/brand/uprl-logo-white.png',   // reversed knockout lockup — CRIMSON / DARK backgrounds
        'mark' => 'images/brand/uprl-mark.png',          // standalone crest mark — collapsed sidebar / compact
    ],

    /*
    |--------------------------------------------------------------------------
    | Favicons / touch icons
    |--------------------------------------------------------------------------
    | Wired into every layout <head> via layouts.partials.favicons.
    */

    'icons' => [
        'favicon' => 'images/brand/favicon.ico',
        'favicon_png' => 'images/brand/favicon-32.png',
        'apple_touch' => 'images/brand/apple-touch-icon.png',
    ],

];
