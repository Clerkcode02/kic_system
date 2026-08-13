<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Domain\Business\Models\Business;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Events\UserRegistered;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterBusiness
{
    /**
     * @param  array{
     *     name: string, email: string, phone: string, password: string,
     *     legal_name: string, registration_number: string,
     *     business_hours: array<string, mixed>, max_bookings_per_day: int,
     * }  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::Provider,
                'status' => UserStatus::Pending,
            ]);

            $user->assignRole(RoleName::ProviderOwner->value);

            Business::create([
                'user_id' => $user->id,
                'legal_name' => $data['legal_name'],
                'registration_number' => $data['registration_number'],
                'verification_status' => BusinessVerificationStatus::Pending,
                'business_hours' => $data['business_hours'],
                'max_bookings_per_day' => $data['max_bookings_per_day'],
            ]);

            UserRegistered::dispatch($user);

            return $user;
        });
    }
}
