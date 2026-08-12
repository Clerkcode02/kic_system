<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

test('every Action in app/Domain/*/Actions has exactly one public method', function () {
    $domainPath = app_path('Domain');

    if (! is_dir($domainPath)) {
        expect(true)->toBeTrue();

        return;
    }

    $finder = (new Finder())->files()->in($domainPath)->path('/\/Actions\//')->name('*.php');

    $offenders = [];

    foreach ($finder as $file) {
        $declaredClasses = get_declared_classes();

        require_once $file->getRealPath();

        $newClasses = array_diff(get_declared_classes(), $declaredClasses);

        foreach ($newClasses as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                continue;
            }

            $publicMethods = array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === $class
                    && ! $method->isConstructor(),
            );

            if (count($publicMethods) !== 1) {
                $offenders[] = $class.' ('.count($publicMethods).' public methods)';
            }
        }
    }

    expect($offenders)->toBe([]);
});
