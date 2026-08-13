<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\Geocoder;
use App\Domain\Business\ValueObjects\Coordinates;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateBusinessProfile implements Action
{
    private const FILLABLE = [
        'legal_name', 'business_hours', 'max_bookings_per_day',
        'street', 'unit', 'city', 'province', 'postal_code',
    ];

    public function __construct(
        private readonly Geocoder $geocoder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Business $business, array $data): Business
    {
        return DB::transaction(function () use ($business, $data): Business {
            $business->fill(Arr::only($data, self::FILLABLE));
            $business->save();

            $coordinates = $this->resolveCoordinates($business, $data);

            if ($coordinates !== null) {
                DB::statement(
                    'UPDATE businesses SET location = ST_MakePoint(?, ?)::geography WHERE id = ?',
                    [$coordinates->lng, $coordinates->lat, $business->id]
                );
            }

            return $business->refresh();
        });
    }

    /**
     * Coordinates from the Leaflet pin are used directly; the geocoder only
     * runs when a full address was submitted with no coordinates
     * (CLAUDE.md provider-management spec).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCoordinates(Business $business, array $data): ?Coordinates
    {
        if (isset($data['lat'], $data['lng'])) {
            return new Coordinates((float) $data['lat'], (float) $data['lng']);
        }

        if (! isset($data['street'], $data['city'], $data['province'], $data['postal_code'])) {
            return null;
        }

        $addressLine = implode(', ', array_filter([
            $data['street'],
            $data['unit'] ?? null,
            $data['city'],
            $data['province'],
            $data['postal_code'],
        ]));

        $coordinates = $this->geocoder->geocode($addressLine);

        if ($coordinates === null) {
            throw new ConflictException('Unable to geocode the provided address.', 'geocoding_failed');
        }

        return $coordinates;
    }
}
