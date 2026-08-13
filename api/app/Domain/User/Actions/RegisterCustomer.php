<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Enums\RoleName;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Events\UserRegistered;
use App\Domain\User\Models\User;

class RegisterCustomer
{
    /**
     * @param  array{name: string, email: string, phone: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => UserRole::Customer,
            'status' => UserStatus::Pending,
        ]);

        $user->assignRole(RoleName::Customer->value);

        UserRegistered::dispatch($user);

        return $user;
    }
}
