<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use App\Domain\Business\ValueObjects\Coordinates;

/**
 * Canada is baked into every implementation — the interface deliberately
 * takes no country parameter (CLAUDE.md §5: Canada-only market for launch).
 */
interface Geocoder
{
    public function geocode(string $address): ?Coordinates;
}
