<?php

declare(strict_types=1);

namespace App\Domain\Business\Actions;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\Business\Models\ProviderAvailabilityOverride;
use App\Domain\Business\Services\AvailabilityCache;
use App\Support\Action;
use Illuminate\Support\Facades\DB;

class ReplaceProviderAvailability implements Action
{
    public function __construct(
        private readonly AvailabilityCache $cache,
    ) {
    }

    /**
     * @param  array{
     *     weekly?: list<array{day_of_week: int, start_time: string, end_time: string, is_active?: bool}>,
     *     overrides?: list<array{date: string, is_blackout?: bool, start_time?: ?string, end_time?: ?string}>,
     * }  $data
     */
    public function handle(Business $business, array $data): Business
    {
        return DB::transaction(function () use ($business, $data): Business {
            if (array_key_exists('weekly', $data)) {
                $business->availability()->delete();

                foreach ($data['weekly'] as $entry) {
                    ProviderAvailability::create([
                        'business_id' => $business->id,
                        'day_of_week' => $entry['day_of_week'],
                        'start_time' => $entry['start_time'],
                        'end_time' => $entry['end_time'],
                        'is_active' => $entry['is_active'] ?? true,
                    ]);
                }
            }

            if (array_key_exists('overrides', $data)) {
                $business->availabilityOverrides()->delete();

                foreach ($data['overrides'] as $entry) {
                    ProviderAvailabilityOverride::create([
                        'business_id' => $business->id,
                        'date' => $entry['date'],
                        'is_blackout' => $entry['is_blackout'] ?? false,
                        'start_time' => $entry['start_time'] ?? null,
                        'end_time' => $entry['end_time'] ?? null,
                    ]);
                }
            }

            $this->cache->flushForBusiness($business->id);

            return $business->load(['availability', 'availabilityOverrides']);
        });
    }
}
