<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the active locale (Section 15 localization groundwork).
 *
 * Resolution order, most specific first:
 *
 *   1. the visitor's own choice, held in the session (set by LocaleController)
 *   2. the institution default from Settings → General
 *   3. whatever config/app.php says
 *
 * A locale is only honoured if it is one this installation actually offers
 * (config('settings.locales')) — an arbitrary value from a session or a query string
 * must never reach App::setLocale, where it becomes a filesystem path segment.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->session()->get('locale');

        if ($this->isOffered($chosen)) {
            app()->setLocale($chosen);

            return $next($request);
        }

        // The settings default already reached config('app.locale') via
        // SettingsServiceProvider, so there is nothing further to do here.
        return $next($request);
    }

    private function isOffered(mixed $locale): bool
    {
        return is_string($locale)
            && $locale !== ''
            && array_key_exists($locale, config('settings.locales', []));
    }
}
