<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates are always the caller's — LocationPicker/geocoding runs
 * client-side once on submit (CLAUDE.md §5); this Action just persists
 * whatever lat/lng it's handed. The first address a user saves becomes
 * their default; is_default=true afterwards demotes the others.
 */
final class StoreAddress implements Action
{
    /**
     * @param  array{label?: ?string, street: string, unit?: ?string, city: string, state_province: string, postal_code: string, lat: float, lng: float, is_default?: bool}  $data
     */
    public function handle(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $makeDefault = ($data['is_default'] ?? false) || ! $user->addresses()->exists();

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                'label' => $data['label'] ?? null,
                'street' => $data['street'],
                'unit' => $data['unit'] ?? null,
                'city' => $data['city'],
                'state_province' => $data['state_province'],
                'postal_code' => $data['postal_code'],
                'country' => 'CA',
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'is_default' => $makeDefault,
            ]);
        });
    }
}
