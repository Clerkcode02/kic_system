<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServicePricingTier;
use App\Support\ConflictException;
use Illuminate\Support\Facades\DB;

class PublishService
{
    /**
     * @param  array{
     *     category_id: string, title: string, description: string,
     *     pricing_type: string, base_price: float|string, estimated_duration_minutes: int,
     *     pricing_tiers?: list<array{tier_name: string, description?: ?string, price: float|string, estimated_duration_minutes?: ?int, sort_order?: int}>,
     * }  $data
     */
    public function handle(Business $business, array $data): Service
    {
        if ($business->verification_status !== BusinessVerificationStatus::Verified) {
            throw new ConflictException('Only a verified business can publish services.', 'business_not_verified');
        }

        return DB::transaction(function () use ($business, $data): Service {
            $service = Service::create([
                'business_id' => $business->id,
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'pricing_type' => $data['pricing_type'],
                'base_price' => $data['base_price'],
                'currency' => 'CAD',
                'estimated_duration_minutes' => $data['estimated_duration_minutes'],
                'is_active' => true,
            ]);

            foreach ($data['pricing_tiers'] ?? [] as $index => $tier) {
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

            return $service->load('pricingTiers', 'category');
        });
    }
}
