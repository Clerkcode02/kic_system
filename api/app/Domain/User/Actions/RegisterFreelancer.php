<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\Freelance\Enums\FreelancerApprovalStatus;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Events\UserRegistered;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterFreelancer
{
    /**
     * @param  array{
     *     name: string, email: string, phone: string, password: string,
     *     headline: string, bio: string, hourly_rate: float|string,
     *     years_experience?: int,
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
                'role' => UserRole::Freelancer,
                'status' => UserStatus::Pending,
            ]);

            $user->assignRole(RoleName::Freelancer->value);

            FreelancerProfile::create([
                'user_id' => $user->id,
                'headline' => $data['headline'],
                'bio' => $data['bio'],
                'hourly_rate' => $data['hourly_rate'],
                'years_experience' => $data['years_experience'] ?? 0,
                'approval_status' => FreelancerApprovalStatus::Pending,
            ]);

            UserRegistered::dispatch($user);

            return $user;
        });
    }
}
