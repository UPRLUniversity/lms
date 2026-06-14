<?php

namespace App\Http\Controllers;

use App\Enums\MediaPurpose;
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

        // Learning preferences (only the digest opt-in for now). Merge so future
        // preference keys aren't clobbered by this single toggle.
        $user->learning_preferences = array_merge($user->learning_preferences ?? [], [
            'email_digest' => $request->boolean('email_digest'),
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
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
