<?php

/*
|--------------------------------------------------------------------------
| Sidebar navigation
|--------------------------------------------------------------------------
|
| Drives the app shell's left sidebar. Each item:
|   - label : visible text (and sr-only label when the sidebar is collapsed)
|   - icon  : name resolved by <x-ui.icon>
|   - route : named route, or null for a not-yet-built placeholder (rendered
|             disabled and muted)
|   - match : pattern for request()->routeIs() to set the active state
|   - roles : roles allowed to see the item. '*' means everyone for now;
|             real role filtering arrives with spatie/laravel-permission in a
|             later section without changing this structure or the markup.
|
*/

return [
    [
        'label' => 'Dashboard',
        'icon' => 'home',
        'route' => 'dashboard',
        'match' => 'dashboard',
        'roles' => ['*'],
    ],
    [
        'label' => 'Courses',
        'icon' => 'book',
        'route' => null,
        'match' => 'courses.*',
        'roles' => ['*'],
    ],
    [
        'label' => 'My Learning',
        'icon' => 'graduation',
        'route' => null,
        'match' => 'learning.*',
        'roles' => ['student'],
    ],
    [
        'label' => 'People',
        'icon' => 'users',
        'route' => null,
        'match' => 'people.*',
        'roles' => ['admin', 'super-admin', 'instructor'],
    ],
    [
        'label' => 'Reports',
        'icon' => 'chart',
        'route' => null,
        'match' => 'reports.*',
        'roles' => ['admin', 'super-admin', 'auditor'],
    ],
    [
        'label' => 'Settings',
        'icon' => 'cog',
        'route' => null,
        'match' => 'settings.*',
        'roles' => ['admin', 'super-admin'],
    ],
];
