<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    /*
     * Event listeners are registered EXPLICITLY, in AppServiceProvider, and nowhere
     * else. Laravel's automatic discovery is switched off here because the two
     * mechanisms silently compound: discovery binds any `handle*` method in
     * app/Listeners to its type-hinted event, so a listener that is also registered by
     * hand fires TWICE per event.
     *
     * That was already happening to MergeGuestCart (found in Section 15) — every login
     * ran the guest-cart merge twice. It survived unnoticed because the merge happens to
     * be idempotent; the audit listener added in this section would not have been so
     * forgiving, recording two entries for one sign-in.
     *
     * One registration mechanism, visible in one file, is worth more than the
     * convenience of discovery.
     */
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // Boot out any session whose account was deactivated after login.
        $middleware->appendToGroup('web', EnsureUserIsActive::class);

        // Baseline security response headers on every web response (Section 15).
        $middleware->appendToGroup('web', SecurityHeaders::class);

        // Apply the visitor's chosen language, if this install offers one (Section 15).
        $middleware->appendToGroup('web', SetLocale::class);

        /*
         * Behind a proxy — an ngrok tunnel in development, nginx or a load balancer in
         * production — the TLS terminates before Laravel sees the request. Without this
         * the framework reads the internal http:// connection and generates http:// URLs
         * on an https:// site: the callback_url handed to Paystack, the webhook URL shown
         * on the payment-methods screen, password-reset links, every asset.
         *
         * TRUSTED_PROXIES defaults to '*' because the app is only ever reached through
         * its own proxy; set it to the balancer's addresses if it is ever exposed directly.
         */
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*') === '*' ? '*' : explode(',', (string) env('TRUSTED_PROXIES')),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // Payment gateways post server-to-server and cannot carry a CSRF token. The
        // endpoint is protected instead by a per-driver signature check (HMAC over the
        // raw body) plus a throttle — see PaymentWebhookController and PaystackGateway.
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments/*',
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
