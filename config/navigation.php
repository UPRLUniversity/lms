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
        'label' => 'Catalogue',
        'icon' => 'book',
        'route' => 'catalogue.index',
        'match' => 'catalogue.*',
        'roles' => ['*'],
    ],
    [
        // Direct/group messaging (Section 9). The read-only auditor never initiates
        // conversations, so it's intentionally excluded.
        'label' => 'Messages',
        'icon' => 'chat',
        'route' => 'messages.index',
        'match' => 'messages.*',
        'roles' => ['student', 'instructor', 'admin', 'super-admin'],
    ],
    [
        'label' => 'Teaching',
        'icon' => 'pencil',
        'route' => 'courses.index',
        'match' => 'courses.*',
        'roles' => ['instructor', 'admin', 'super-admin', 'auditor'],
    ],
    [
        'label' => 'Academic structure',
        'icon' => 'graduation',
        'route' => 'admin.faculties.index',
        'match' => 'admin.faculties.*',
        'roles' => ['admin', 'super-admin', 'auditor'],
    ],
    [
        // The CPR/DPR qualification structure. Sits next to "Academic structure"
        // because the two are the same kind of thing from different angles: one is
        // who teaches a course, the other is what it counts toward.
        'label' => 'Programmes',
        'icon' => 'layers',
        'route' => 'admin.programmes.index',
        'match' => 'admin.programmes.*',
        'roles' => ['admin', 'super-admin', 'auditor'],
    ],
    [
        'label' => 'Grade scales',
        'icon' => 'list',
        'route' => 'admin.grade-scales.index',
        'match' => 'admin.grade-scales.*',
        'roles' => ['admin', 'super-admin'],
    ],
    [
        // The store (Section 12). Orders and payment methods are admin-only; discount
        // codes are visible to instructors too, who may issue them for their own
        // courses (CouponPolicy decides, not this list).
        'label' => 'Orders',
        'icon' => 'receipt',
        'route' => 'admin.orders.index',
        'match' => 'admin.orders.*',
        'roles' => ['admin', 'super-admin'],
    ],
    [
        'label' => 'Discount codes',
        'icon' => 'tag',
        'route' => 'admin.coupons.index',
        'match' => 'admin.coupons.*',
        'roles' => ['admin', 'super-admin', 'instructor'],
    ],
    [
        'label' => 'Payment methods',
        'icon' => 'credit-card',
        'route' => 'admin.payment-methods.index',
        'match' => 'admin.payment-methods.*',
        'roles' => ['admin', 'super-admin'],
    ],
    [
        'label' => 'My Learning',
        'icon' => 'graduation',
        'route' => 'learning.index',
        'match' => 'learning.*',
        'roles' => ['student'],
    ],
    [
        'label' => 'My Certificates',
        'icon' => 'certificate',
        'route' => 'certificates.mine',
        'match' => 'certificates.mine',
        'roles' => ['student'],
    ],
    [
        'label' => 'Certificates',
        'icon' => 'certificate',
        'route' => 'admin.certificates.index',
        'match' => 'admin.certificates.*',
        'roles' => ['admin', 'super-admin', 'auditor'],
    ],
    [
        'label' => 'Certificate templates',
        'icon' => 'certificate',
        'route' => 'admin.certificate-templates.index',
        'match' => 'admin.certificate-templates.*',
        'roles' => ['admin', 'super-admin'],
    ],
    [
        'label' => 'Approvals',
        'icon' => 'user-plus',
        'route' => 'enrollments.approvals',
        'match' => 'enrollments.approvals',
        'roles' => ['instructor', 'admin', 'super-admin'],
    ],
    [
        'label' => 'Grading',
        'icon' => 'clipboard-check',
        'route' => 'grading.index',
        'match' => 'grading.*',
        'roles' => ['instructor', 'admin', 'super-admin', 'auditor'],
    ],
    [
        'label' => 'Rubrics',
        'icon' => 'list',
        'route' => 'rubrics.index',
        'match' => 'rubrics.*',
        'roles' => ['instructor', 'admin', 'super-admin', 'auditor'],
    ],
    [
        // Auditor is intentionally included — it sees the people list read-only.
        'label' => 'People',
        'icon' => 'users',
        'route' => 'admin.users.index',
        'match' => 'admin.users.*',
        'roles' => ['admin', 'super-admin', 'auditor'],
    ],
    [
        'label' => 'Invitations',
        'icon' => 'inbox',
        'route' => 'admin.invitations.index',
        'match' => 'admin.invitations.*',
        'roles' => ['admin', 'super-admin'],
    ],
    [
        // Forum moderation queue (Section 9) — reported posts for admin review.
        'label' => 'Reported posts',
        'icon' => 'flag',
        'route' => 'admin.forum-reports.index',
        'match' => 'admin.forum-reports.*',
        'roles' => ['admin', 'super-admin'],
    ],
    [
        'label' => 'Reports',
        'icon' => 'chart',
        'route' => 'reports.index',
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
