<?php

declare(strict_types=1);

namespace App\Domain\Business\Services;

use App\Domain\Business\ValueObjects\Coordinates;

/**
 * Test double bound in place of {@see GeoapifyGeocoder}. Records every call
 * so a test can assert the Canada country filter was applied — the real
 * provider bakes it into the outgoing HTTP request, this one records it
 * directly since it never leaves the process.
 */
final class FakeGeocoder implements Geocoder
{
    /**
     * @var list<array{address: string, country_filter: string}>
     */
    public array $recordedCalls = [];

    public function __construct(
        private ?Coordinates $result = new Coordinates(43.6532, -79.3832),
    ) {
    }

    public function geocode(string $address): ?Coordinates
    {
        $this->recordedCalls[] = ['address' => $address, 'country_filter' => 'ca'];

        return $this->result;
    }

    public function returning(?Coordinates $coordinates): self
    {
        $this->result = $coordinates;

        return $this;
    }
}
