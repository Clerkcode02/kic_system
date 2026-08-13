<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Service;
use App\Support\Action;

/**
 * Services are deactivated, not hard-deleted — bookings hold a non-nullable
 * FK to services, so a real delete would either cascade-destroy booking
 * history or fail the constraint outright.
 */
class DeactivateService implements Action
{
    public function handle(Service $service): Service
    {
        $service->update(['is_active' => false]);

        return $service;
    }
}
