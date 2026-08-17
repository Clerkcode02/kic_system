<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

/**
 * CLAUDE.md §4: "On top of roles, every model has a Policy enforcing
 * ownership ... No ad-hoc ownership `if`s in controllers." Role middleware
 * alone can't stop customer A from reading/mutating customer B's booking —
 * only an authorization check against the specific route-bound model can.
 *
 * This scans every `api/v1/...` route whose URI binds a model
 * (`{booking}`, `{quotation}`, ...) and sits behind `api.protected`, and
 * asserts the request is authorized against that specific model: either
 * the injected FormRequest's authorize() checks it (the normal path —
 * `$this->user()->can(<ability>, $this->route('<param>'))`), or the
 * controller itself does (`$this->authorize(`, `Gate::`, `->can(`).
 * A FormRequest that just `return true;`s, or a controller with neither,
 * means the endpoint is authenticated but not authorized — a hole one
 * `abort_if` fix behind a real Policy check.
 */
function routeMethodSource(string $class, string $method): string
{
    $reflection = new ReflectionMethod($class, $method);
    $file = $reflection->getFileName();
    $start = $reflection->getStartLine();
    $end = $reflection->getEndLine();

    if ($file === false || $start === false || $end === false) {
        return '';
    }

    $lines = file($file);

    return implode('', array_slice($lines, $start - 1, $end - $start + 1));
}

function sourceChecksAuthorization(string $source): bool
{
    return (bool) preg_match('/->authorize\(|Gate::|->can\(|::allows\(|Cannot::/', $source);
}

it('authorizes every protected route that binds a specific model against that model', function () {
    $violations = [];

    $routes = collect(Route::getRoutes())->filter(function ($route) {
        $uri = $route->uri();

        return str_starts_with($uri, 'api/v1/')
            && preg_match('/\{[a-zA-Z_]+\}/', $uri) === 1
            && in_array('api.protected', $route->gatherMiddleware(), true);
    });

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $action = $route->getAction();
        $uses = $action['uses'] ?? null;

        if (! is_string($uses) || ! str_contains($uses, '@') && ! class_exists($uses)) {
            continue;
        }

        [$class, $method] = str_contains($uses, '@')
            ? explode('@', $uses, 2)
            : [$uses, '__invoke'];

        if (! class_exists($class) || ! method_exists($class, $method)) {
            continue;
        }

        $reflectionMethod = new ReflectionMethod($class, $method);
        $authorized = false;
        $formRequestChecked = false;

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();

            if (! is_subclass_of($typeName, FormRequest::class)) {
                continue;
            }

            $formRequestChecked = true;

            if (! method_exists($typeName, 'authorize')) {
                continue;
            }

            $authorizeReflection = new ReflectionMethod($typeName, 'authorize');

            if ($authorizeReflection->getDeclaringClass()->getName() !== $typeName) {
                // Inherited default FormRequest::authorize() (returns true) — not an override.
                continue;
            }

            $source = routeMethodSource($typeName, 'authorize');

            if (sourceChecksAuthorization($source)) {
                $authorized = true;
            }
        }

        if (! $authorized) {
            $controllerSource = routeMethodSource($class, $method);

            if (sourceChecksAuthorization($controllerSource)) {
                $authorized = true;
            }
        }

        if (! $authorized) {
            $violations[] = sprintf(
                '%s %s -> %s@%s%s',
                implode('|', $route->methods()),
                $route->uri(),
                $class,
                $method,
                $formRequestChecked ? ' (FormRequest present but authorize() has no ownership check)' : ' (no FormRequest, no authorize()/can() in controller)',
            );
        }
    }

    expect($violations)->toBe([]);
})->skip(fn () => app()->environment('testing') === false, 'requires the full route table');

/**
 * The test above only looks at routes with a `{model}` URI binding, since
 * those are the ones where an ad-hoc `if` could let customer A touch
 * customer B's booking. Routes with no URI param at all (`POST /bookings`,
 * `POST /disputes`, admin bulk-approve endpoints, ...) have no specific
 * instance to check ownership against, but per CLAUDE.md §4/§6 they still
 * need *some* authorization decision — typically a FormRequest whose
 * authorize() calls `$this->user()->can('create', Model::class)` against a
 * Policy's create() method (mirrors CLAUDE.md's Policy convention).
 *
 * A no-param route can legitimately skip that and rely on `api.protected`
 * (auth:sanctum) alone when the endpoint's only "authorization" question is
 * "are you logged in" — there is no create()-style ability to check because
 * the write is inherently scoped to `$request->user()` with no other party
 * who could be impersonated (e.g. "add an address to my own account",
 * "mark my own notifications read"). Every such route is enumerated in
 * SELF_SCOPED_NO_PARAM_ROUTES below with the reason it's safe, rather than
 * silently passing every no-param route — a new no-param route not on this
 * list, and without a real authorize() check, fails this test.
 */
it('authorizes every protected route with no URI param, or documents why it is self-scoped', function () {
    // method|uri => reason this genuinely needs no ownership/ability check
    // beyond "is authenticated" (already enforced by api.protected).
    $selfScopedNoParamRoutes = [
        // Every write here operates on `$request->user()` (or that user's
        // own business/freelancer profile) with no other party's data
        // reachable through the endpoint — there is no ability to check
        // beyond "is this a logged-in, non-suspended user."
        'GET|api/v1/me/addresses' => 'lists only the authenticated user\'s own addresses',
        'POST|api/v1/me/addresses' => 'creates an address owned by the authenticated user, not a specified target',
        'GET|api/v1/me/notification-preferences' => 'reads only the authenticated user\'s own preferences',
        'PATCH|api/v1/me/notification-preferences' => 'updates only the authenticated user\'s own preferences',
        'GET|api/v1/notifications' => 'lists only the authenticated user\'s own notifications (ListNotificationsQuery scopes to $request->user())',
        'POST|api/v1/notifications/read-all' => 'marks only $request->user()->unreadNotifications(), no other party reachable',
        'GET|api/v1/bookings' => 'IndexBookingRequest scopes results to the authenticated user\'s own bookings',
        'GET|api/v1/provider/me' => 'reads the authenticated provider\'s own business profile',
        'GET|api/v1/provider/me/dashboard' => 'the authenticated provider\'s own dashboard',
        'GET|api/v1/provider/me/availability' => 'reads the authenticated provider\'s own availability',
        'GET|api/v1/provider/me/earnings' => 'the authenticated provider\'s own earnings ledger',
        'GET|api/v1/freelancer/me/dashboard' => 'the authenticated freelancer\'s own dashboard',
        'GET|api/v1/freelancer/me/contracts' => 'lists only the authenticated freelancer\'s own contracts',
        'GET|api/v1/freelancer/me/earnings' => 'the authenticated freelancer\'s own earnings ledger',
        'GET|api/v1/freelancers/me/proposals' => 'lists only the authenticated freelancer\'s own proposals',
    ];

    $violations = [];
    $seenAllowListedRoutes = [];

    $routes = collect(Route::getRoutes())->filter(function ($route) {
        $uri = $route->uri();

        return str_starts_with($uri, 'api/v1/')
            && preg_match('/\{[a-zA-Z_]+\}/', $uri) !== 1
            && in_array('api.protected', $route->gatherMiddleware(), true);
    });

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        // HEAD is Laravel's automatic companion to every GET and carries no
        // separate authorization decision, so it's excluded from the key —
        // otherwise every GET route's key would need a redundant "GET|HEAD"
        // entry instead of plain "GET".
        $key = sprintf('%s|%s', implode('|', array_diff($route->methods(), ['HEAD'])), $route->uri());

        $action = $route->getAction();
        $uses = $action['uses'] ?? null;

        if (! is_string($uses) || ! str_contains($uses, '@') && ! class_exists($uses)) {
            continue;
        }

        [$class, $method] = str_contains($uses, '@')
            ? explode('@', $uses, 2)
            : [$uses, '__invoke'];

        if (! class_exists($class) || ! method_exists($class, $method)) {
            continue;
        }

        $reflectionMethod = new ReflectionMethod($class, $method);
        $authorized = false;

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();

            if (! is_subclass_of($typeName, FormRequest::class)) {
                continue;
            }

            if (! method_exists($typeName, 'authorize')) {
                continue;
            }

            $authorizeReflection = new ReflectionMethod($typeName, 'authorize');

            if ($authorizeReflection->getDeclaringClass()->getName() !== $typeName) {
                continue;
            }

            $source = routeMethodSource($typeName, 'authorize');

            if (sourceChecksAuthorization($source)) {
                $authorized = true;
            }
        }

        if (! $authorized) {
            $controllerSource = routeMethodSource($class, $method);

            if (sourceChecksAuthorization($controllerSource)) {
                $authorized = true;
            }
        }

        if ($authorized) {
            continue;
        }

        if (array_key_exists($key, $selfScopedNoParamRoutes)) {
            $seenAllowListedRoutes[$key] = true;

            continue;
        }

        $violations[] = sprintf(
            '%s %s -> %s@%s (no real authorize()/can() check, and not on the documented self-scoped allow-list)',
            implode('|', $route->methods()),
            $route->uri(),
            $class,
            $method,
        );
    }

    expect($violations)->toBe([]);

    // Keep the allow-list honest: every entry must actually match a route
    // that exists and would otherwise fail, or it's dead documentation.
    $staleEntries = array_diff(array_keys($selfScopedNoParamRoutes), array_keys($seenAllowListedRoutes));
    expect($staleEntries)->toBe([]);
})->skip(fn () => app()->environment('testing') === false, 'requires the full route table');
