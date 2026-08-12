<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

test('no file in app/ calls Model::unguard', function () {
    $offenders = [];

    $finder = (new Finder())->files()->in(app_path())->name('*.php');

    foreach ($finder as $file) {
        if (str_contains($file->getContents(), 'Model::unguard')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});
