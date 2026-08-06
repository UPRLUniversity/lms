<?php

use App\Enums\Role;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Admin\CertificateTemplateController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FacultyController;
use App\Http\Controllers\Admin\ForumReportController;
use App\Http\Controllers\Admin\GradeScaleController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\ProgrammeController;
use App\Http\Controllers\Admin\ProgrammePartController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Assessments\AssessmentContentController;
use App\Http\Controllers\Assessments\AssessmentController;
use App\Http\Controllers\Assessments\AssessmentInsightController;
use App\Http\Controllers\Assessments\AttemptController;
use App\Http\Controllers\Assessments\AttemptResultController;
use App\Http\Controllers\Assessments\GradingController;
use App\Http\Controllers\Assessments\QuestionCategoryController;
use App\Http\Controllers\Assessments\QuestionController;
use App\Http\Controllers\Assignments\AssignmentController;
use App\Http\Controllers\Assignments\AssignmentGradingController;
use App\Http\Controllers\Assignments\RubricController;
use App\Http\Controllers\Assignments\SubmissionController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\Commerce\CartController;
use App\Http\Controllers\Commerce\CheckoutController;
use App\Http\Controllers\Commerce\OrderController;
use App\Http\Controllers\Commerce\PaymentWebhookController;
use App\Http\Controllers\Communication\ConversationController;
use App\Http\Controllers\Communication\ForumController;
use App\Http\Controllers\Communication\ForumPostController;
use App\Http\Controllers\Communication\MessageController;
use App\Http\Controllers\Courses\AdminEnrollmentController;
use App\Http\Controllers\Courses\AnnouncementController;
use App\Http\Controllers\Courses\BulkEnrollmentController;
use App\Http\Controllers\Courses\CourseController;
use App\Http\Controllers\Courses\CourseCurriculumController;
use App\Http\Controllers\Courses\CurriculumVisibilityController;
use App\Http\Controllers\Courses\CourseProgressController;
use App\Http\Controllers\Courses\CourseWorkflowController;
use App\Http\Controllers\Courses\EnrollmentApprovalController;
use App\Http\Controllers\Courses\EnrollmentController;
use App\Http\Controllers\Courses\LearnController;
use App\Http\Controllers\Courses\LearningHistoryController;
use App\Http\Controllers\Courses\LessonController;
use App\Http\Controllers\Courses\ModuleController;
use App\Http\Controllers\Courses\MyLearningController;
use App\Http\Controllers\Courses\RosterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorUploadController;
use App\Http\Controllers\Grades\GradebookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstructorAnalyticsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ProgrammeController as PublicProgrammeController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\ReportDownloadController;
use App\Http\Controllers\VerificationController;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site (Section 13) — homepage & programme landing pages
|--------------------------------------------------------------------------
| The whole guest journey starts here and stays open to strangers: homepage →
| programme → course → cart. The only door that asks for an account is checkout,
| and that one shows a sign-in panel rather than bouncing (CheckoutController).
|
| An inactive programme 404s, so switching one off in admin removes it from the
| public site the same second.
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/programmes', [PublicProgrammeController::class, 'index'])->name('programmes.index');
Route::get('/programmes/{programme}', [PublicProgrammeController::class, 'show'])->name('programmes.show');

/*
|--------------------------------------------------------------------------
| Legal pages (Section 15) — linked from BOTH footers
|--------------------------------------------------------------------------
| Placeholders with the real structure in place, so the institution's counsel can
| supply the wording without anyone touching routing or layout. Reachable by guests
| because a visitor must be able to read the terms BEFORE creating an account.
*/
Route::view('/terms', 'legal.terms')->name('legal.terms');
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');

/*
| Language switcher (Section 15). Open to guests — someone reading the marketing
| site should be able to change language before signing up. It writes one session
| key; the controller rejects any locale this install does not offer.
*/
Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('locale.update');

/*
|--------------------------------------------------------------------------
| Public course catalogue (guest-visible)
|--------------------------------------------------------------------------
| Only published + publicly-visible courses are ever listed; CatalogueController
| 404s a direct slug to anything else.
*/
Route::get('/courses', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/courses/{course}', [CatalogueController::class, 'show'])->name('catalogue.show');

/*
|--------------------------------------------------------------------------
| Cart (guest-visible) & payment webhooks (Section 12)
|--------------------------------------------------------------------------
| The cart is deliberately open to signed-out visitors: they browse the public
| catalogue, fill a basket, and only need an account at checkout, at which point
| MergeGuestCart carries the basket into their profile. Requiring an account before
| anything can be added is the friction the public catalogue exists to remove.
|
| The webhook is public by necessity — gateways cannot authenticate as a user. It is
| CSRF-exempt (bootstrap/app.php), throttled, and every driver verifies a signature
| before trusting a single field of the body.
*/
Route::controller(CartController::class)->group(function () {
    Route::get('/cart', 'index')->name('cart.index');
    Route::delete('/cart', 'clear')->name('cart.clear');

    // ORDER MATTERS. These literal segments must be declared before the
    // /cart/{course} wildcard below, or "POST /cart/coupon" matches the wildcard
    // first and tries to resolve a course whose slug is "coupon".
    Route::post('/cart/coupon', 'applyCoupon')->name('cart.coupon.apply');
    Route::delete('/cart/coupon', 'removeCoupon')->name('cart.coupon.remove');
    Route::delete('/cart/items/{item}', 'destroy')->name('cart.destroy');

    Route::post('/cart/{course}', 'store')->name('cart.store');
});

Route::post('/webhooks/payments/{method}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('payments.webhook');

/*
|--------------------------------------------------------------------------
| Checkout & orders (auth)
|--------------------------------------------------------------------------
| An order has to belong to somebody, so this is where the guest journey asks for an
| account. Note there is no 'verified' middleware: making someone confirm an e-mail
| before they can pay would lose the sale, and payment itself proves rather more about
| them than a mail click does.
|
| GET /checkout is deliberately OUTSIDE the auth group (Section 13): a signed-out
| buyer sees their order summary with an inline "log in to continue" panel instead of
| being thrown at a bare login form having lost all context. Everything that actually
| writes an order still requires an account.
*/
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');

Route::middleware('auth')->group(function () {
    // Throttled (Section 15): placing an order talks to a payment gateway and writes a
    // money record, so a double-click storm or a scripted loop must not be able to open
    // a hundred pending orders. Generous enough that a genuine retry after a failed card
    // is never blocked.
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('checkout.store');
    Route::get('/checkout/{order}/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

/*
|--------------------------------------------------------------------------
| Public certificate verification portal (no auth)
|--------------------------------------------------------------------------
| Manual serial entry and a QR deep-link both resolve through the same lookup.
| Throttled so the serial space can't be brute-forced for a hit; a miss never
| reveals whether it "almost" matched a real format (see CertificateVerificationService).
*/
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/verify', [VerificationController::class, 'index'])->name('verify.index');
    Route::post('/verify', [VerificationController::class, 'lookup'])->name('verify.lookup');
    Route::get('/verify/{serial}', [VerificationController::class, 'show'])->name('verify.show');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');

    /*
    | In-app bell (Section 8): unread badge + recent list (polled), the mark-read /
    | open redirect, mark-all-read and the full filterable history page.
    */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // Policy-gated download of a private file.
    Route::get('/media/{media}/download', [MediaController::class, 'download'])
        ->middleware('can:view,media')
        ->name('media.download');

    // In-editor image uploads (TinyMCE) → MediaUploadService. Throttled (Section 15):
    // an authenticated author is trusted, but an upload endpoint is still the cheapest
    // way to fill a disk or a Cloudinary quota by accident.
    Route::post('/editor/upload', [EditorUploadController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('editor.upload');
});

/*
|--------------------------------------------------------------------------
| Student enrolment — self-enrol, My Learning, self-withdraw
|--------------------------------------------------------------------------
| Verified accounts only. The service resolves a self-enrolment to active / pending /
| waitlisted; withdrawing is authorized by the EnrollmentPolicy (owner or staff).
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-learning', [MyLearningController::class, 'index'])->name('learning.index');
    Route::get('/history', [LearningHistoryController::class, 'index'])->name('learning.history');
    Route::post('/courses/{course}/enrol', [EnrollmentController::class, 'store'])->name('enrollment.store');
    Route::delete('/enrolments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollment.withdraw');

    /*
    | The learning player. Course binds by slug; lesson by id (membership re-checked
    | server-side). The literal "congratulations" segment is declared before the
    | {lesson} catch so it never resolves as a lesson id.
    */
    Route::get('/learn/{course}', [LearnController::class, 'resume'])->name('learn.resume');
    Route::get('/learn/{course}/congratulations', [LearnController::class, 'congratulations'])->name('learn.congratulations');
    Route::get('/learn/{course}/grades', [GradebookController::class, 'mine'])->name('learn.grades');
    Route::get('/learn/{course}/announcements', [AnnouncementController::class, 'index'])->name('learn.announcements');
    Route::get('/learn/{course}/{lesson}', [LearnController::class, 'show'])->name('learn.show');
    Route::post('/learn/{course}/{lesson}/complete', [LearnController::class, 'complete'])->name('learn.complete');
    Route::post('/learn/{course}/{lesson}/incomplete', [LearnController::class, 'incomplete'])->name('learn.incomplete');
    Route::post('/learn/{course}/{lesson}/position', [LearnController::class, 'position'])->name('learn.position');

    /*
    | Certificates (Section 7) — "My Certificates", gated download and the pending-
    | render poll used by the completion screen. Ownership is checked by CertificatePolicy
    | (also lets the admin registry re-download through the same route).
    */
    Route::get('/certificates', [CertificateController::class, 'mine'])->name('certificates.mine');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::get('/certificates/{certificate}/status', [CertificateController::class, 'status'])->name('certificates.status');
});

/*
|--------------------------------------------------------------------------
| Communication (Section 9) — course forums & messaging
|--------------------------------------------------------------------------
| Course forums live under /courses/{course}/forum (course membership gates access;
| the read-only auditor reads but never posts). Messaging lives under /messages
| (participation gates every read/write; the auditor never initiates). Posting is
| rate-limited so a runaway client can't flood a thread or an inbox.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    // Course forum — the thread list, a thread, and opening one. "create" is declared
    // before the {thread} catch so the literal segment always wins.
    Route::get('courses/{course}/forum', [ForumController::class, 'index'])->name('forum.index');
    Route::get('courses/{course}/forum/create', [ForumController::class, 'create'])->name('forum.create');
    Route::post('courses/{course}/forum', [ForumController::class, 'store'])
        ->middleware('throttle:30,1')->name('forum.store');
    Route::get('courses/{course}/forum/{thread}', [ForumController::class, 'show'])->name('forum.show');

    // Thread moderation (instructors/admins) + accept-answer (author or instructor).
    Route::post('courses/{course}/forum/{thread}/pin', [ForumController::class, 'pin'])->name('forum.pin');
    Route::post('courses/{course}/forum/{thread}/lock', [ForumController::class, 'lock'])->name('forum.lock');
    Route::post('courses/{course}/forum/{thread}/answer', [ForumController::class, 'answer'])->name('forum.answer');
    Route::delete('courses/{course}/forum/{thread}', [ForumController::class, 'destroy'])->name('forum.destroy');

    // Replies — post (rate-limited), remove (author/moderator), report (any member).
    Route::post('courses/{course}/forum/{thread}/posts', [ForumPostController::class, 'store'])
        ->middleware('throttle:30,1')->name('posts.store');
    Route::delete('forum-posts/{post}', [ForumPostController::class, 'destroy'])->name('posts.destroy');
    Route::post('forum-posts/{post}/report', [ForumPostController::class, 'report'])->name('posts.report');

    // Messaging — the inbox, the composer, a thread, and sending. "create" is declared
    // before the {conversation} catch.
    Route::get('messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('messages/create', [ConversationController::class, 'create'])->name('messages.create');
    Route::post('messages', [ConversationController::class, 'store'])
        ->middleware('throttle:30,1')->name('messages.store');
    Route::get('messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:60,1')->name('messages.send');

    // Entry points — "Message" a person, and an instructor's "message all enrolled".
    Route::post('messages/to/{user}', [ConversationController::class, 'startWith'])->name('messages.start');
    Route::post('courses/{course}/message-all', [ConversationController::class, 'messageCourse'])->name('messages.course');
});

/*
|--------------------------------------------------------------------------
| Admin — forum moderation queue (Section 9)
|--------------------------------------------------------------------------
| Reported posts for admin review. Reporting is open to any member (a moderation
| hook); acting on a report is admin-only (reviewForumReports gate in the controller).
*/
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('forum-reports', [ForumReportController::class, 'index'])->name('forum-reports.index');
        Route::post('forum-reports/{report}/dismiss', [ForumReportController::class, 'dismiss'])->name('forum-reports.dismiss');
        Route::post('forum-posts/{post}/remove', [ForumReportController::class, 'removePost'])->name('forum-reports.remove');
    });

/*
|--------------------------------------------------------------------------
| Admin area — user management & invitations
|--------------------------------------------------------------------------
| Gated by the users.view permission (admins + read-only auditors). Mutating
| actions are additionally authorized per-action by UserPolicy, so an auditor
| reaches the list but every write is rejected.
*/
Route::middleware(['auth', 'verified', 'permission:users.view'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/status', [UserController::class, 'setStatus'])->name('users.status');

        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin — academic structure (faculties & departments)
|--------------------------------------------------------------------------
| Admins manage; auditors view read-only. Each action is authorized in the
| controller via Faculty/DepartmentPolicy (super-admin bypasses).
*/
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('faculties', [FacultyController::class, 'index'])->name('faculties.index');
        Route::get('faculties/create', [FacultyController::class, 'create'])->name('faculties.create');
        Route::post('faculties', [FacultyController::class, 'store'])->name('faculties.store');
        Route::get('faculties/{faculty}/edit', [FacultyController::class, 'edit'])->name('faculties.edit');
        Route::put('faculties/{faculty}', [FacultyController::class, 'update'])->name('faculties.update');
        Route::delete('faculties/{faculty}', [FacultyController::class, 'destroy'])->name('faculties.destroy');

        Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin — programmes & parts (Section 11)
|--------------------------------------------------------------------------
| The qualification structure (CPR, DPR, Professional Variant …) courses are
| packaged and examined under — a second axis alongside faculty/department.
| Admins manage, auditors and instructors read (an instructor places their own
| course into a part from the course builder, but cannot edit the structure).
| Each action is authorized in the controller via Programme/ProgrammePartPolicy.
*/
Route::middleware(['auth', 'verified', 'permission:programmes.view'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('programmes', [ProgrammeController::class, 'index'])->name('programmes.index');
        Route::get('programmes/create', [ProgrammeController::class, 'create'])->name('programmes.create');
        Route::post('programmes', [ProgrammeController::class, 'store'])->name('programmes.store');
        Route::get('programmes/{programme}/edit', [ProgrammeController::class, 'edit'])->name('programmes.edit');
        Route::put('programmes/{programme}', [ProgrammeController::class, 'update'])->name('programmes.update');
        Route::delete('programmes/{programme}', [ProgrammeController::class, 'destroy'])->name('programmes.destroy');

        Route::get('programme-parts/create', [ProgrammePartController::class, 'create'])->name('programme-parts.create');
        Route::post('programme-parts', [ProgrammePartController::class, 'store'])->name('programme-parts.store');
        Route::get('programme-parts/{part}/edit', [ProgrammePartController::class, 'edit'])->name('programme-parts.edit');
        Route::put('programme-parts/{part}', [ProgrammePartController::class, 'update'])->name('programme-parts.update');
        Route::delete('programme-parts/{part}', [ProgrammePartController::class, 'destroy'])->name('programme-parts.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin — the store (Section 12)
|--------------------------------------------------------------------------
| Payment methods hold live gateway credentials, so they are admin-only and are
| deliberately NOT extended to the auditor — "read-only observer" should not include
| reading payment secrets. Orders and coupons carry their own permissions.
|
| Discount codes are the exception to the admin-only pattern: an instructor may issue
| course-scoped codes for a course they teach, so this group is gated by policy inside
| the controller rather than by permission middleware.
*/
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::post('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid'])->name('orders.mark-paid');
        Route::post('orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin — system settings (Section 15)
|--------------------------------------------------------------------------
| Super-admin ONLY (settings.manage), a deliberately narrower door than the rest of
| /admin: these values reach branding, security, session length and money formatting
| across the whole institution.
|
| The optional {group} segment is the tab, so a tab is linkable and a save can
| redirect back to the one it came from.
|
| Note what is NOT here: gateway credentials stay on the Section 12 payment-methods
| screen (encrypted, admin-only) and the grade-band grid stays on the Section 6.5
| scale admin. This page links to both rather than re-implementing either.
*/
Route::middleware(['auth', 'verified', 'permission:settings.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('settings/{group?}', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

/*
|--------------------------------------------------------------------------
| Admin — audit trail (Section 15)
|--------------------------------------------------------------------------
| Gated on audit.view, which the read-only auditor holds by construction (every
| ".view" permission) — reading the record of what happened is exactly that role's
| job. Admins hold it too; the super-admin passes via Gate::before.
|
| GET only, by design. The trail is APPEND-ONLY: there is no delete or edit route
| here, and AuditActivity refuses both at the model level even if one were added.
*/
Route::middleware(['auth', 'verified', 'permission:audit.view'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('audit/export', [AuditController::class, 'export'])->name('audit.export');
    });

/*
|--------------------------------------------------------------------------
| Admin — grade scales (Section 6.5)
|--------------------------------------------------------------------------
| Admin-only (grade-scales.manage). Archive-not-delete once referenced.
*/
Route::middleware(['auth', 'verified', 'permission:grade-scales.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('grade-scales', [GradeScaleController::class, 'index'])->name('grade-scales.index');
        Route::get('grade-scales/create', [GradeScaleController::class, 'create'])->name('grade-scales.create');
        Route::post('grade-scales', [GradeScaleController::class, 'store'])->name('grade-scales.store');
        Route::get('grade-scales/{gradeScale}/edit', [GradeScaleController::class, 'edit'])->name('grade-scales.edit');
        Route::put('grade-scales/{gradeScale}', [GradeScaleController::class, 'update'])->name('grade-scales.update');
        Route::post('grade-scales/{gradeScale}/archive', [GradeScaleController::class, 'archive'])->name('grade-scales.archive');
        Route::post('grade-scales/{gradeScale}/restore', [GradeScaleController::class, 'restore'])->name('grade-scales.restore');
    });

/*
|--------------------------------------------------------------------------
| Admin — certificate templates (Section 7)
|--------------------------------------------------------------------------
| Admin-only, like grade scales — template design isn't part of the auditor's
| record-keeping view (that's the registry below).
*/
Route::middleware(['auth', 'verified', 'permission:certificate-templates.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('certificate-templates', [CertificateTemplateController::class, 'index'])->name('certificate-templates.index');
        Route::get('certificate-templates/create', [CertificateTemplateController::class, 'create'])->name('certificate-templates.create');
        Route::post('certificate-templates', [CertificateTemplateController::class, 'store'])->name('certificate-templates.store');
        Route::post('certificate-templates/signature-upload', [CertificateTemplateController::class, 'uploadSignature'])->name('certificate-templates.signature-upload');
        Route::get('certificate-templates/{certificateTemplate}/edit', [CertificateTemplateController::class, 'edit'])->name('certificate-templates.edit');
        Route::put('certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'update'])->name('certificate-templates.update');
        Route::get('certificate-templates/{certificateTemplate}/preview', [CertificateTemplateController::class, 'preview'])->name('certificate-templates.preview');
    });

/*
|--------------------------------------------------------------------------
| Admin — certificate registry (Section 7)
|--------------------------------------------------------------------------
| Viewing (including the read-only auditor) needs certificates.view; every mutation
| (issue/re-issue/revoke/restore) is additionally gated by certificates.manage inside
| the controller via CertificatePolicy.
*/
Route::middleware(['auth', 'verified', 'permission:certificates.view'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('certificates', [AdminCertificateController::class, 'index'])->name('certificates.index');
    });

Route::middleware(['auth', 'verified', 'permission:certificates.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('certificates/issue', [AdminCertificateController::class, 'issue'])->name('certificates.issue');
        Route::post('certificates/{certificate}/reissue', [AdminCertificateController::class, 'reissue'])->name('certificates.reissue');
        Route::post('certificates/{certificate}/revoke', [AdminCertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::post('certificates/{certificate}/restore', [AdminCertificateController::class, 'restore'])->name('certificates.restore');
    });

/*
|--------------------------------------------------------------------------
| Report centre (Section 10) — admin + read-only auditor
|--------------------------------------------------------------------------
| Dashboards live at /dashboard; this is the exportable reporting suite. The whole
| area is gated on reports.view (auditors hold it — nothing here mutates). Export is a
| POST so it can carry the full filter set (course arrays, e-mail cohorts); a large
| export is queued and delivered by an in-app "report ready" notification, whose link
| hits the Policy-gated download route.
*/
Route::middleware(['auth', 'verified', 'permission:reports.view'])
    ->prefix('admin/reports')
    ->name('reports.')
    ->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('exports/{generatedReport}', ReportDownloadController::class)->name('download');
        Route::get('{report}', [ReportController::class, 'show'])->name('show');
        Route::post('{report}/export', [ReportController::class, 'export'])->name('export');
    });

/*
|--------------------------------------------------------------------------
| Course builder & publishing workflow (instructors + admins)
|--------------------------------------------------------------------------
| The instructor list, the builder (settings + curriculum), per-type lesson
| authoring (AJAX), drag-reorder persistence and the draft → review → published
| workflow. Every action is authorized through CoursePolicy.
*/
Route::middleware(['auth', 'verified'])
    ->prefix('manage')
    ->group(function () {
        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('courses/create', [CourseController::class, 'create'])->name('courses.create');
        Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
        Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        // Curriculum — outline partial (AJAX refresh) + whole-outline reorder.
        Route::get('courses/{course}/curriculum', [CourseCurriculumController::class, 'show'])->name('courses.curriculum');
        Route::post('courses/{course}/curriculum/reorder', [CourseCurriculumController::class, 'reorder'])->name('courses.curriculum.reorder');

        // Hide/restore any curriculum item — the safe alternative to deleting student work.
        Route::patch('courses/{course}/curriculum/{type}/{id}/visibility', [CurriculumVisibilityController::class, 'update'])
            ->whereIn('type', ['module', 'lesson', 'assessment', 'assignment'])
            ->whereNumber('id')
            ->name('courses.curriculum.visibility');

        // Modules (AJAX).
        Route::post('courses/{course}/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::patch('courses/{course}/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::delete('courses/{course}/modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

        // Lessons (AJAX; store/update are multipart for file-type lessons).
        Route::get('courses/{course}/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
        Route::post('courses/{course}/modules/{module}/lessons', [LessonController::class, 'store'])->name('lessons.store');
        Route::post('courses/{course}/lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
        Route::delete('courses/{course}/lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

        // Publishing workflow.
        Route::post('courses/{course}/submit', [CourseWorkflowController::class, 'submit'])->name('courses.submit');
        Route::post('courses/{course}/publish', [CourseWorkflowController::class, 'publish'])->name('courses.publish');
        Route::post('courses/{course}/return', [CourseWorkflowController::class, 'returnToDraft'])->name('courses.return');
        Route::post('courses/{course}/archive', [CourseWorkflowController::class, 'archive'])->name('courses.archive');
        Route::post('courses/{course}/restore', [CourseWorkflowController::class, 'restore'])->name('courses.restore');

        /*
        | Enrolment management (instructors + admins; each action policy-gated).
        */

        // Approval queue — admins + lead instructors.
        Route::get('approvals', [EnrollmentApprovalController::class, 'index'])->name('enrollments.approvals');
        Route::post('approvals/bulk', [EnrollmentApprovalController::class, 'bulkApprove'])->name('enrollments.bulk-approve');
        Route::post('approvals/{enrollment}/approve', [EnrollmentApprovalController::class, 'approve'])->name('enrollments.approve');
        Route::post('approvals/{enrollment}/reject', [EnrollmentApprovalController::class, 'reject'])->name('enrollments.reject');

        // Direct staff enrolment — posted from the roster and from a user's admin page.
        Route::post('enrollments', [AdminEnrollmentController::class, 'store'])->name('enrollment.admin.store');

        // Bulk CSV import (admins): template, upload→preview, confirm.
        Route::get('enrollments/import', [BulkEnrollmentController::class, 'create'])->name('enrollments.bulk.create');
        Route::get('enrollments/import/template', [BulkEnrollmentController::class, 'template'])->name('enrollments.bulk.template');
        Route::post('enrollments/import/preview', [BulkEnrollmentController::class, 'preview'])->name('enrollments.bulk.preview');
        Route::post('enrollments/import', [BulkEnrollmentController::class, 'store'])->name('enrollments.bulk.store');

        // Per-course learner progress — % per student, last activity, completion heat-strip.
        Route::get('courses/{course}/progress', [CourseProgressController::class, 'index'])->name('courses.progress');

        // Per-course roster — tabbed, searchable, with capacity meter + CSV export.
        Route::get('courses/{course}/roster', [RosterController::class, 'index'])->name('courses.roster');
        Route::get('courses/{course}/roster/export', [RosterController::class, 'export'])->name('courses.roster.export');
        Route::delete('courses/{course}/roster/{enrollment}', [RosterController::class, 'destroy'])->name('courses.roster.withdraw');

        // Instructor gradebook matrix (Section 6.5) — students × gradebook items.
        Route::get('courses/{course}/gradebook', [GradebookController::class, 'matrix'])->name('courses.gradebook');
        Route::get('courses/{course}/gradebook/export', [GradebookController::class, 'export'])->name('courses.gradebook.export');
        Route::post('courses/{course}/gradebook/{user}/recompute', [GradebookController::class, 'recompute'])->name('courses.gradebook.recompute');

        // Per-course analytics drill-down (Section 10) — progress, assessment stats,
        // knowledge gain and the class grade distribution.
        Route::get('courses/{course}/analytics', [InstructorAnalyticsController::class, 'show'])->name('courses.analytics');

        // Course announcements (Section 8) — composed from the builder's Announcements tab.
        Route::post('courses/{course}/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('courses/{course}/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

/*
|--------------------------------------------------------------------------
| Assessment authoring (instructors + admins)
|--------------------------------------------------------------------------
| The question bank, the assessment builder (settings + fixed list / pool rules),
| publishing and preview. Every action is authorized through Question/Assessment
| policies (course ownership). Assessment slugs are unique per course, so the
| {course}/{assessment} routes use scoped bindings.
*/
Route::middleware(['auth', 'verified'])
    ->prefix('manage')
    ->scopeBindings()
    ->group(function () {
        // Question bank (course-scoped).
        Route::get('courses/{course}/questions', [QuestionController::class, 'index'])->name('questions.index');
        Route::get('courses/{course}/questions/create', [QuestionController::class, 'create'])->name('questions.create');
        Route::get('courses/{course}/questions/import', [QuestionController::class, 'importForm'])->name('questions.import.form');
        Route::post('courses/{course}/questions/import', [QuestionController::class, 'import'])->name('questions.import');
        Route::post('courses/{course}/questions', [QuestionController::class, 'store'])->name('questions.store');
        Route::get('courses/{course}/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
        Route::put('courses/{course}/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
        Route::post('courses/{course}/questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
        Route::delete('courses/{course}/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

        // Categories (AJAX).
        Route::post('courses/{course}/question-categories', [QuestionCategoryController::class, 'store'])->name('question-categories.store');
        Route::put('courses/{course}/question-categories/{category}', [QuestionCategoryController::class, 'update'])->name('question-categories.update');
        Route::delete('courses/{course}/question-categories/{category}', [QuestionCategoryController::class, 'destroy'])->name('question-categories.destroy');

        // Assessments.
        Route::post('courses/{course}/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('courses/{course}/assessments/{assessment}/edit', [AssessmentController::class, 'edit'])->name('assessments.edit');
        Route::put('courses/{course}/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::delete('courses/{course}/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
        Route::post('courses/{course}/assessments/{assessment}/publish', [AssessmentController::class, 'publish'])->name('assessments.publish');
        Route::post('courses/{course}/assessments/{assessment}/unpublish', [AssessmentController::class, 'unpublish'])->name('assessments.unpublish');
        Route::get('courses/{course}/assessments/{assessment}/preview', [AssessmentController::class, 'preview'])->name('assessments.preview');

        // Assessment content (fixed questions + pool rules) — AJAX.
        Route::put('courses/{course}/assessments/{assessment}/questions', [AssessmentContentController::class, 'syncQuestions'])->name('assessments.questions.sync');
        Route::post('courses/{course}/assessments/{assessment}/pool-rules', [AssessmentContentController::class, 'storeRule'])->name('assessments.pool-rules.store');
        Route::put('courses/{course}/assessments/{assessment}/pool-rules/{rule}', [AssessmentContentController::class, 'updateRule'])->name('assessments.pool-rules.update');
        Route::delete('courses/{course}/assessments/{assessment}/pool-rules/{rule}', [AssessmentContentController::class, 'destroyRule'])->name('assessments.pool-rules.destroy');

        // Class-level pre/post knowledge-gain insight.
        Route::get('courses/{course}/insights', [AssessmentInsightController::class, 'index'])->name('assessments.insights');
    });

/*
|--------------------------------------------------------------------------
| Assignment authoring (instructors + admins; auditors read-only)
|--------------------------------------------------------------------------
| The rubric library + grid builder and the assignment builder (settings, rubric,
| resources, publish gate). Assignment slugs are unique per course → scoped bindings.
*/
Route::middleware(['auth', 'verified'])
    ->prefix('manage')
    ->group(function () {
        Route::get('rubrics', [RubricController::class, 'index'])->name('rubrics.index');
        Route::get('rubrics/create', [RubricController::class, 'create'])->name('rubrics.create');
        Route::post('rubrics', [RubricController::class, 'store'])->name('rubrics.store');
        Route::get('rubrics/{rubric}/edit', [RubricController::class, 'edit'])->name('rubrics.edit');
        Route::put('rubrics/{rubric}', [RubricController::class, 'update'])->name('rubrics.update');
        Route::delete('rubrics/{rubric}', [RubricController::class, 'destroy'])->name('rubrics.destroy');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('manage')
    ->scopeBindings()
    ->group(function () {
        Route::post('courses/{course}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('courses/{course}/assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
        Route::put('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
        Route::post('courses/{course}/assignments/{assignment}/publish', [AssignmentController::class, 'publish'])->name('assignments.publish');
        Route::post('courses/{course}/assignments/{assignment}/unpublish', [AssignmentController::class, 'unpublish'])->name('assignments.unpublish');
        Route::post('courses/{course}/assignments/{assignment}/resources', [AssignmentController::class, 'storeResource'])->name('assignments.resources.store');
        Route::delete('courses/{course}/assignments/{assignment}/resources/{media}', [AssignmentController::class, 'destroyResource'])->name('assignments.resources.destroy');
    });

/*
|--------------------------------------------------------------------------
| Manual grading queue (instructors + admins)
|--------------------------------------------------------------------------
| Submitted attempts with essay / scenario-essay answers awaiting a human, plus the
| assignment grading workspace (queue → split view → grade & next / return). Each
| action is authorized through the Attempt/Submission policies.
*/
Route::middleware(['auth', 'verified'])
    ->prefix('manage')
    ->group(function () {
        Route::get('grading', [GradingController::class, 'index'])->name('grading.index');

        // Assignment grading — declared before {attempt} so the literal segment wins.
        Route::get('grading/assignments', [AssignmentGradingController::class, 'index'])->name('grading.assignments.index');
        Route::get('grading/assignments/{submission}', [AssignmentGradingController::class, 'show'])->name('grading.assignments.show');
        Route::put('grading/assignments/{submission}', [AssignmentGradingController::class, 'update'])->name('grading.assignments.update');
        Route::post('grading/assignments/{submission}/return', [AssignmentGradingController::class, 'returnSubmission'])->name('grading.assignments.return');

        Route::get('grading/{attempt}', [GradingController::class, 'show'])->name('grading.show');
        Route::put('grading/{attempt}', [GradingController::class, 'update'])->name('grading.update');
    });

/*
|--------------------------------------------------------------------------
| Taking assessments (students) — inside the player frame
|--------------------------------------------------------------------------
| The start screen, the timed one-question-per-screen runner (autosave/flag/submit),
| the result + review and the attempt history. AttemptService enforces the window,
| attempt cap, frozen layout and server-authoritative timer.
*/
Route::middleware(['auth', 'verified'])
    ->scopeBindings()
    ->group(function () {
        Route::get('/learn/{course}/assessment/{assessment}', [AttemptController::class, 'start'])->name('assessments.start');
        Route::post('/learn/{course}/assessment/{assessment}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
        Route::get('/learn/{course}/assessment/{assessment}/history', [AttemptResultController::class, 'history'])->name('attempts.history');

        Route::get('/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
        Route::post('/attempts/{attempt}/answer', [AttemptController::class, 'answer'])->name('attempts.answer');
        Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
        Route::get('/attempts/{attempt}/result', [AttemptResultController::class, 'show'])->name('attempts.result');

        /*
        | Assignments (students) — the brief + submit + version history, inside the
        | player frame. A version stays readable forever via submissions.show.
        */
        Route::get('/learn/{course}/assignment/{assignment}', [SubmissionController::class, 'show'])->name('assignments.show');
        Route::post('/learn/{course}/assignment/{assignment}/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
        Route::get('/submissions/{submission}', [SubmissionController::class, 'showVersion'])->name('submissions.show');
    });

// Short-lived signed access to a private file (PrivateFileService::temporaryUrl).
Route::get('/media/{media}/temporary', [MediaController::class, 'temporary'])
    ->middleware('signed')
    ->name('media.temporary');

// Living design reference — registered only in local/testing so it never ships to production.
if (app()->environment(['local', 'testing'])) {
    Route::get('/styleguide', fn () => view('styleguide'))->name('styleguide');

    // Branded e-mail previews (render the real notifications). e.g.
    // /mail-preview/invitation · /mail-preview/verify · /mail-preview/reset
    Route::get('/mail-preview/{type?}', function (string $type = 'invitation') {
        // A throwaway, unsaved user with a fake key so signed routes can build.
        $user = (new User(['name' => 'Ada Lovelace', 'email' => 'preview@uprl.test']))
            ->forceFill(['id' => 1]);

        $invitation = (new UserInvitation([
            'name' => 'Ada Lovelace',
            'email' => 'ada@uprl.test',
            'role' => Role::Instructor->value,
            'expires_at' => now()->addDays(7),
        ]))->forceFill(['id' => 1]);

        $mail = match ($type) {
            'verify' => (new VerifyEmail)->toMail($user),
            'reset' => (new ResetPassword('preview-token'))->toMail($user),
            default => (new UserInvitationNotification($invitation, 'preview-token'))->toMail($invitation),
        };

        return $mail->render();
    })->name('mail.preview');
}

require __DIR__.'/auth.php';
