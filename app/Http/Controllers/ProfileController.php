<?php

namespace App\Http\Controllers;

use App\Enums\MediaPurpose;
use App\Enums\NotificationType;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'title' => $data['title'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Save the notification preferences matrix: per-type email/in-app toggles plus
     * the digest frequency. Critical types are excluded from the payload (their
     * toggle renders locked/checked), so they simply keep defaulting to on.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();

        foreach (NotificationType::cases() as $type) {
            if ($type->isCritical()) {
                continue;
            }

            $user->setNotificationPreference($type, [
                'email' => $request->boolean("email.{$type->value}"),
                'in_app' => $request->boolean("in_app.{$type->value}"),
            ]);
        }

        $prefs = $user->learning_preferences ?? [];
        $prefs['email_digest'] = $request->boolean('email_digest');
        $user->learning_preferences = $prefs;

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    /**
     * Upload (or replace) the avatar. Goes through MediaUploadService — never the
     * storage SDK directly — and removes the previous avatar so only one is kept.
     */
    public function updateAvatar(Request $request, MediaUploadService $media): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Drop the old avatar (file + record) before attaching the new one.
        if ($old = $user->avatar()) {
            $media->destroy($old);
        }

        $media->upload($request->file('avatar'), MediaPurpose::Avatars, $user);

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * Remove the avatar and fall back to initials.
     */
    public function destroyAvatar(Request $request, MediaUploadService $media): RedirectResponse
    {
        if ($avatar = $request->user()->avatar()) {
            $media->destroy($avatar);
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
