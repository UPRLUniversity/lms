<?php

namespace App\Support\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * The route → permission map (Section 15).
 *
 * Answers one question for every route in the application: "what stops the wrong person
 * calling this?" — and, for mutating routes, refuses to accept "nothing" as an answer.
 *
 * A route counts as GUARDED when it is protected by at least one of:
 *
 *   middleware   auth / permission: / role: / signed / can: on the route or its group
 *   policy       an authorize() / Gate:: call inside the controller action
 *   deliberate   an entry in the public allow-list below, each with a stated reason
 *
 * The third category is the important one. A handful of routes MUST be reachable by a
 * stranger — a payment gateway posting a webhook cannot authenticate as a user — and the
 * honest way to handle them is to name them and say why, not to let them hide among the
 * unguarded. Anything not on the list and not otherwise protected is a finding.
 *
 * Used by `php artisan audit:routes` and asserted by RoutePermissionMapTest, so the map
 * cannot quietly rot as routes are added.
 */
class RouteGuardAudit
{
    /** HTTP verbs that change state. GET/HEAD are read paths and judged separately. */
    public const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Mutating routes with no middleware guard and no authorize() call, each with the
     * reason that is CORRECT — either because the route must be reachable by a stranger,
     * or because it is guarded by a mechanism this scanner cannot see (a signed URL, a
     * single-use hashed token) rather than by an authorization check.
     *
     * Every entry is a deliberate decision with a stated justification. Keyed by route
     * name, or by "METHOD uri" for the routes the framework leaves unnamed.
     *
     * @var array<string, string>
     */
    public const PUBLIC_BY_DESIGN = [
        // Authentication itself cannot require authentication. Each is rate-limited.
        'login' => 'Sign-in. Throttled per e-mail+IP by LoginRequest::ensureIsNotRateLimited.',
        'POST login' => 'Sign-in (unnamed by Breeze). Guarded by LoginRequest::authorize + its throttle.',
        'POST register' => 'Self-registration (unnamed by Breeze). Open by design — it is how a learner joins — '
            .'and it can only ever create an unverified account with the student role; no input chooses a role.',
        'password.email' => 'Password-reset request. Throttled; reveals nothing about whether the address exists.',
        'password.store' => 'Password reset. Requires a single-use, expiring token verified against its hash.',
        'logout' => 'Ends the caller\'s own session. Nothing to escalate.',
        'invitations.accept.store' => 'Invitation acceptance. Guarded by a single-use token compared against its '
            .'stored hash in InvitationService::resolve — an unguessable secret only the invitee received. '
            .'Not middleware, so the scanner cannot see it.',

        // Framework-registered file routes. Both verify a signed relative URL inside the
        // handler (Illuminate\Filesystem\ServeFile / ReceiveFile) — a closure, so again
        // invisible to a controller scan. The private disk's visibility is 'private',
        // which is what makes that signature check mandatory rather than optional.
        'storage.private.upload' => 'Framework disk upload endpoint. Requires a valid signed relative URL '
            .'(ReceiveFile::hasValidSignature) before writing anything.',

        // Commerce: the guest journey is the point of the public catalogue.
        'cart.store' => 'A signed-out visitor filling a basket. Holds no money and grants no access; merged into their account at login.',
        'cart.destroy' => 'Removes an item from the caller\'s OWN cart, resolved from their session/cookie token.',
        'cart.clear' => 'Empties the caller\'s own cart.',
        'cart.coupon.apply' => 'Applies a code to the caller\'s own cart. The discount is re-resolved server-side at checkout regardless.',
        'cart.coupon.remove' => 'Removes a code from the caller\'s own cart.',

        // Gateways cannot hold a session.
        'payments.webhook' => 'Payment gateway callback. Authenticated by per-driver HMAC signature over the raw body, CSRF-exempt by necessity, throttled.',

        // Public certificate verification.
        'verify.lookup' => 'Certificate verification by serial. Throttled; a miss reveals nothing about near-matches.',
    ];

    /**
     * Every route, with its guards and verdict.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function map(): Collection
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->map(fn (Route $route) => $this->describe($route))
            ->sortBy('uri')
            ->values();
    }

    /**
     * Mutating routes with no guard at all and no stated reason — the set that must
     * always be empty.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unguardedMutating(): Collection
    {
        return $this->map()->filter(
            fn (array $row) => $row['mutating'] && ! $row['guarded']
        )->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Route $route): array
    {
        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $middleware = $this->middleware($route);
        $name = $route->getName();

        $mutating = array_intersect($methods, self::MUTATING) !== [];

        $viaMiddleware = $this->guardingMiddleware($middleware);
        $viaPolicy = $this->authorizesInController($route);
        $viaAllowList = $this->allowListReason($route, $name);

        return [
            'method' => implode('|', $methods),
            'uri' => $route->uri(),
            'name' => $name,
            'action' => $this->actionLabel($route),
            'middleware' => $middleware,
            'mutating' => $mutating,
            'guards' => $viaMiddleware,
            'policy' => $viaPolicy,
            'public_reason' => $viaAllowList,
            'guarded' => $viaMiddleware !== [] || $viaPolicy || $viaAllowList !== null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function middleware(Route $route): array
    {
        try {
            return array_values(array_unique(app('router')->gatherRouteMiddleware($route)));
        } catch (\Throwable) {
            return $route->middleware();
        }
    }

    /**
     * Middleware that actually restricts WHO may call the route. `web` and `throttle`
     * are excluded deliberately: a session cookie and a rate limit are not authorization.
     *
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function guardingMiddleware(array $middleware): array
    {
        $guards = [];

        foreach ($middleware as $entry) {
            $entry = is_string($entry) ? $entry : '';

            $isGuard = $entry === 'auth'
                || str_starts_with($entry, 'auth:')
                || str_starts_with($entry, 'permission:')
                || str_starts_with($entry, 'role:')
                || str_starts_with($entry, 'role_or_permission:')
                || str_starts_with($entry, 'can:')
                || $entry === 'signed'
                || str_contains($entry, 'Authenticate')
                || str_contains($entry, 'PermissionMiddleware')
                || str_contains($entry, 'RoleMiddleware')
                || str_contains($entry, 'ValidateSignature');

            if ($isGuard) {
                $guards[] = $entry;
            }
        }

        return array_values(array_unique($guards));
    }

    /**
     * Whether the controller action authorizes for itself.
     *
     * Deliberately a source scan rather than static analysis: the alternative is
     * executing the action, and a heuristic that errs toward "look again" is the right
     * trade for a guard-rail whose failure mode is a false alarm rather than a hole.
     */
    private function authorizesInController(Route $route): bool
    {
        $action = $route->getAction('uses');

        if (! is_string($action) || ! str_contains($action, '@')) {
            return false;   // closure route — no controller to inspect
        }

        [$class, $method] = explode('@', $action);

        if (! class_exists($class)) {
            return false;
        }

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\Throwable) {
            return false;
        }

        $file = $reflection->getFileName();

        if (! $file || ! File::exists($file)) {
            return false;
        }

        $lines = File::lines($file)
            ->slice($reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1)
            ->implode("\n");

        // Also treat a FormRequest's authorize() as a guard: the request is resolved
        // before the action body runs, and a false there is a 403 just the same.
        $viaFormRequest = $this->authorizesInFormRequest($reflection);

        return $viaFormRequest
            || str_contains($lines, '$this->authorize(')
            || str_contains($lines, 'Gate::authorize(')
            || str_contains($lines, 'Gate::allows(')
            || str_contains($lines, 'Gate::denies(')
            || str_contains($lines, '->cannot(')
            || str_contains($lines, 'abort_unless(')
            || str_contains($lines, 'abort_if(');
    }

    private function authorizesInFormRequest(\ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if (! class_exists($class) || ! is_subclass_of($class, FormRequest::class)) {
                continue;
            }

            // A FormRequest that overrides authorize() is making a decision.
            $reflection = new \ReflectionClass($class);

            if ($reflection->hasMethod('authorize')
                && $reflection->getMethod('authorize')->getDeclaringClass()->getName() === $class) {
                return true;
            }
        }

        return false;
    }

    private function allowListReason(Route $route, ?string $name): ?string
    {
        if ($name !== null && isset(self::PUBLIC_BY_DESIGN[$name])) {
            return self::PUBLIC_BY_DESIGN[$name];
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $key = $method.' '.$route->uri();

            if (isset(self::PUBLIC_BY_DESIGN[$key])) {
                return self::PUBLIC_BY_DESIGN[$key];
            }
        }

        return null;
    }

    private function actionLabel(Route $route): string
    {
        $action = $route->getAction('uses');

        if (is_string($action)) {
            return str_replace('App\\Http\\Controllers\\', '', $action);
        }

        return 'Closure';
    }
}
