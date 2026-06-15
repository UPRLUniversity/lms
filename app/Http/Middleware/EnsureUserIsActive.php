<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces out any authenticated user whose account has been deactivated since they
 * logged in. The login gate (LoginRequest) stops them at the door; this stops an
 * already-open session the moment an admin flips is_active off.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('This account has been deactivated. Please contact an administrator.'),
            ]);
        }

        return $next($request);
    }
}
