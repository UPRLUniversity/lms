<?php

use App\Enums\Role;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\EditorUploadController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
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
