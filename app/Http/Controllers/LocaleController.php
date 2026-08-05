<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The language switcher's endpoint (Section 15).
 *
 * Open to guests by design — someone reading the public marketing site should be able
 * to change language before they have an account. It writes one session key and nothing
 * else, so there is no state to protect.
 *
 * The switcher itself only renders when Settings → General enables it, but this route
 * validates independently rather than trusting that: a locale is accepted only if this
 * installation actually offers it. Without that check the value flows into
 * App::setLocale() and becomes part of a translation FILE PATH.
 */
class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(
            array_key_exists($locale, config('settings.locales', [])),
            404,
        );

        $request->session()->put('locale', $locale);

        return back();
    }
}
