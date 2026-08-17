<?php

declare(strict_types=1);

use App\Domain\Freelance\Models\PortfolioItem;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('lets an admin fetch a short-TTL signed URL for a freelancer portfolio item', function () {
    Storage::fake('s3');

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $portfolioItem = PortfolioItem::factory()->create(['file_path' => 'portfolio-items/some-freelancer/work.jpg']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/admin/freelancers/portfolio-items/{$portfolioItem->id}/url")
        ->assertOk();

    expect($response->json('data.url'))->not->toBeEmpty()
        ->and($response->json('data.expires_at'))->not->toBeEmpty();
});

it('denies a non-admin from fetching a portfolio item URL', function () {
    Storage::fake('s3');

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(RoleName::Freelancer->value);
    $token = $freelancerUser->createToken('device')->plainTextToken;

    $portfolioItem = PortfolioItem::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/admin/freelancers/portfolio-items/{$portfolioItem->id}/url")
        ->assertForbidden();
});

it('rejects an unauthenticated request for a portfolio item URL', function () {
    $portfolioItem = PortfolioItem::factory()->create();

    $this->getJson("/api/v1/admin/freelancers/portfolio-items/{$portfolioItem->id}/url")
        ->assertUnauthorized();
});
