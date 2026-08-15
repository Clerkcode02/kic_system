<?php

declare(strict_types=1);

namespace App\Domain\User\Queries;

use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListMyAddressesQuery
{
    /**
     * @return Collection<int, Address>
     */
    public function handle(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }
}
