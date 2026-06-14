<?php

use App\Http\Controllers\EditorUploadController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
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

    // Policy-gated download of a private file.
    Route::get('/media/{media}/download', [MediaController::class, 'download'])
        ->middleware('can:view,media')
        ->name('media.download');

    // In-editor image uploads (TinyMCE) → MediaUploadService.
    Route::post('/editor/upload', [EditorUploadController::class, 'store'])->name('editor.upload');
});

// Short-lived signed access to a private file (PrivateFileService::temporaryUrl).
Route::get('/media/{media}/temporary', [MediaController::class, 'temporary'])
    ->middleware('signed')
    ->name('media.temporary');

// Living design reference — registered only in local/testing so it never ships to production.
if (app()->environment(['local', 'testing'])) {
    Route::get('/styleguide', fn () => view('styleguide'))->name('styleguide');
}

require __DIR__.'/auth.php';
