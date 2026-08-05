<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers (Section 15 hardening sweep).
 *
 * Deliberately NOT a Content-Security-Policy. A meaningful CSP for this app would have
 * to allow TinyMCE's inline styles, Alpine's inline expressions, Bunny Fonts and the
 * Cloudinary image host, and a policy loose enough to permit all of that buys very
 * little while being easy to get subtly wrong. The honest position is to ship the
 * headers that are unambiguously correct and record CSP as a known gap with its
 * prerequisites, which docs/hardening-report.md does — rather than ship a permissive
 * policy that merely looks like protection.
 *
 * HSTS is emitted only over HTTPS: sending it on a plaintext response is meaningless,
 * and sending it from a local http:// dev server would pin the developer's browser to
 * HTTPS for localhost, which is a genuinely painful thing to undo.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // The app frames nothing and should never be framed — clickjacking on the
            // grading and checkout screens is the concern.
            'X-Frame-Options' => 'SAMEORIGIN',

            // Never let a browser second-guess a declared content type. Matters most for
            // user-uploaded files served through the private-media routes.
            'X-Content-Type-Options' => 'nosniff',

            // Don't leak a learner's course/lesson URL to third-party hosts, while
            // keeping full referrers within the site.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // No feature of this app needs any of these.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',

            // Legacy header, still honoured by some corporate proxies.
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        foreach ($headers as $name => $value) {
            // Never overwrite a header a specific response set on purpose — the
            // framework's file-serving responses ship their own restrictive CSP and
            // sandbox, and those are stricter than anything set here.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
