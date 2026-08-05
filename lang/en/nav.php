<?php

/*
|--------------------------------------------------------------------------
| Shared chrome — navigation, topbar, account menu
|--------------------------------------------------------------------------
|
| Section 15 localization groundwork. A pragmatic first pass covering the shared
| chrome, authentication and the learner-facing pages: the strings a second
| language would need first, not every string in the application.
|
| See docs/localization.md for how to add a language.
|
*/

return [

    // Sidebar / primary navigation. Keys mirror config/navigation.php labels, so a
    // translated nav needs no change to that file's structure.
    'dashboard' => 'Dashboard',
    'catalogue' => 'Catalogue',
    'messages' => 'Messages',
    'teaching' => 'Teaching',
    'academic_structure' => 'Academic structure',
    'programmes' => 'Programmes',
    'grade_scales' => 'Grade scales',
    'orders' => 'Orders',
    'discount_codes' => 'Discount codes',
    'payment_methods' => 'Payment methods',
    'my_learning' => 'My Learning',
    'my_certificates' => 'My Certificates',
    'certificates' => 'Certificates',
    'certificate_templates' => 'Certificate templates',
    'approvals' => 'Approvals',
    'grading' => 'Grading',
    'rubrics' => 'Rubrics',
    'people' => 'People',
    'invitations' => 'Invitations',
    'reported_posts' => 'Reported posts',
    'reports' => 'Reports',
    'audit_trail' => 'Audit trail',
    'settings' => 'Settings',

    // Topbar & account menu.
    'skip_to_content' => 'Skip to content',
    'open_navigation' => 'Open navigation',
    'toggle_sidebar' => 'Toggle sidebar width',
    'account_menu' => 'Account menu',
    'notifications' => 'Notifications',
    'mark_all_read' => 'Mark all read',
    'view_all_notifications' => 'View all notifications',
    'all_caught_up' => "You're all caught up.",
    'profile' => 'Profile',
    'log_out' => 'Log Out',
    'cart' => 'Cart',
    'cart_items' => '{1} Cart, 1 item|[2,*] Cart, :count items',

    // Language switcher (hidden until Settings → General enables it).
    'language' => 'Language',
    'choose_language' => 'Choose a language',
];
