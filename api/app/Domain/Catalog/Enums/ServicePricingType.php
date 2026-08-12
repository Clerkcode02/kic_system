<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ServicePricingType: string
{
    case Hourly = 'hourly';
    case Fixed = 'fixed';
    case Package = 'package';
    case Inspection = 'inspection';
}
