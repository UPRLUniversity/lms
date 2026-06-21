<?php

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FacultyController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\Courses\AdminEnrollmentController;
use App\Http\Controllers\Courses\BulkEnrollmentController;
use App\Http\Controllers\Courses\CourseController;
use App\Http\Controllers\Courses\CourseCurriculumController;
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
use App\Http\Controllers\EditorUploadController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Models\Course;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public course catalogue (guest-visible)
|--------------------------------------------------------------------------
| Only published + publicly-visible courses are ever listed; CatalogueController
| 404s a direct slug to anything else.
*/
Route::get('/courses', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/courses/{course}', [CatalogueController::class, 'show'])->name('catalogue.show');

Route::get('/dashboard', function () {
    $user = auth()->user();
    $isStaff = $user->hasAnyRole([Role::Instructor->value, Role::Admin->value, Role::SuperAdmin->value]);
    $isAdmin = $user->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);

    $data = ['isStaff' => $isStaff, 'isAdmin' => $isAdmin, 'isAuditor' => $user->hasRole(Role::Auditor->value)];

    if ($isAdmin || $user->hasRole(Role::Auditor->value)) {
        // Whole-platform view for admins/auditors.
        $data['stats'] = [
            'courses' => Course::count(),
            'inReview' => Course::where('status', CourseStatus::Review->value)->count(),
            'published' => Course::where('status', CourseStatus::Published->value)->count(),
            'people' => User::count(),
        ];
    } elseif ($user->hasRole(Role::Instructor->value)) {
        // The instructor's own courses by status.
        $mine = Course::forInstructor($user);
        $data['stats'] = [
            'courses' => (clone $mine)->count(),
            'drafts' => (clone $mine)->where('status', CourseStatus::Draft->value)->count(),
            'inReview' => (clone $mine)->where('status', CourseStatus::Review->value)->count(),
            'published' => (clone $mine)->where('status', CourseStatus::Published->value)->count(),
        ];
    } else {
        // Student: their real learning at a glance + courses to continue.
        $enrollments = $user->enrollments()
            ->with(['course.department', 'course.media'])
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
                EnrollmentStatus::Completed->value,
            ])
            ->get();

        $data['stats'] = [
            'inProgress' => $enrollments->where('status', EnrollmentStatus::Active)->count(),
            'completed' => $enrollments->where('status', EnrollmentStatus::Completed)->count(),
            'awaiting' => $enrollments->whereIn('status', [
                EnrollmentStatus::Pending,
                EnrollmentStatus::Waitlisted,
            ])->count(),
        ];

        // The active courses to "continue", most recently enrolled first.
        $data['continueLearning'] = $enrollments
            ->where('status', EnrollmentStatus::Active)
            ->sortByDesc('enrolled_at')
            ->take(3)
            ->values();
    }

    return view('dashboard', $data);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    // Policy-gated download of a private file.
    Route::get('/media/{media}/download', [MediaController::class, 'download'])
        ->middleware('can:view,media')
        ->name('media.download');

    // In-editor image uploads (TinyMCE) → MediaUploadService.
    Route::post('/editor/upload', [EditorUploadController::class, 'store'])->name('editor.upload');
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
    Route::get('/learn/{course}/{lesson}', [LearnController::class, 'show'])->name('learn.show');
    Route::post('/learn/{course}/{lesson}/complete', [LearnController::class, 'complete'])->name('learn.complete');
    Route::post('/learn/{course}/{lesson}/incomplete', [LearnController::class, 'incomplete'])->name('learn.incomplete');
    Route::post('/learn/{course}/{lesson}/position', [LearnController::class, 'position'])->name('learn.position');
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
