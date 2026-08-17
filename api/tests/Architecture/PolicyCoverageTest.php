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
