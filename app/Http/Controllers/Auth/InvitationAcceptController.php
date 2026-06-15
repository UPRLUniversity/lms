<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The invitee's side of the flow: open the signed link, set a password, get an
 * active account with the granted role. A used or expired link resolves to null
 * and shows the branded "invalid invitation" screen — it can never be replayed.
 */
class InvitationAcceptController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    public function show(Request $request, UserInvitation $invitation): View
    {
        $resolved = $this->invitations->resolve($invitation->id, (string) $request->query('token'));

        if (! $resolved) {
            return view('auth.invitation-invalid');
        }

        return view('auth.invitation-accept', [
            'invitation' => $resolved,
            'token' => (string) $request->query('token'),
        ]);
    }

    public function store(Request $request, UserInvitation $invitation): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $resolved = $this->invitations->resolve($invitation->id, $validated['token']);

        if (! $resolved) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('This invitation is no longer valid. Please ask for a new one.')]);
        }

        $user = $this->invitations->accept($resolved, $validated['password']);

        Auth::login($user);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Welcome to '.config('brand.name').'! Your account is ready.');
    }
}
