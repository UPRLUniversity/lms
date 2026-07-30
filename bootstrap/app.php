<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Boot out any session whose account was deactivated after login.
        $middleware->appendToGroup('web', EnsureUserIsActive::class);

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
