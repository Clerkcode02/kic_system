<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use App\Domain\Business\ValueObjects\Coordinates;
use Illuminate\Support\Facades\Http;

final class GeoapifyGeocoder implements Geocoder
{
    private const ENDPOINT = 'https://api.geoapify.com/v1/geocode/search';

    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    public function geocode(string $address): ?Coordinates
    {
        $response = Http::get(self::ENDPOINT, [
            'text' => $address,
            // Country-filtered at the provider level, per CLAUDE.md §5 — never
            // geocode against the whole world and post-filter.
            'filter' => 'countrycode:ca',
            'limit' => 1,
            'apiKey' => $this->apiKey,
        ]);

        if ($response->failed()) {
            return null;
        }

        $feature = $response->json('features.0');

        if (! is_array($feature)) {
            return null;
        }

        $coordinates = $feature['geometry']['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        [$lng, $lat] = $coordinates;

        return new Coordinates((float) $lat, (float) $lng);
    }
}
