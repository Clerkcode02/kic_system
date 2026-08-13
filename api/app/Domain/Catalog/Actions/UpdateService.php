<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServicePricingTier;
use App\Support\Action;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateService implements Action
{
    private const FILLABLE = [
        'category_id', 'title', 'description', 'pricing_type',
        'base_price', 'estimated_duration_minutes', 'is_active',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data): Service {
            $service->fill(Arr::only($data, self::FILLABLE));
            $service->save();

            if (array_key_exists('pricing_tiers', $data)) {
                $service->pricingTiers()->delete();

                foreach ($data['pricing_tiers'] as $index => $tier) {
                    ServicePricingTier::create([
                        'service_id' => $service->id,
                        'tier_name' => $tier['tier_name'],
                        'description' => $tier['description'] ?? null,
                        'price' => $tier['price'],
                        'currency' => 'CAD',
                        'estimated_duration_minutes' => $tier['estimated_duration_minutes'] ?? null,
                        'sort_order' => $tier['sort_order'] ?? $index,
                    ]);
                }
            }

            return $service->load('pricingTiers', 'category');
        });
    }
}
