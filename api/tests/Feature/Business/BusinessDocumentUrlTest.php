<?php

declare(strict_types=1);

use App\Domain\Business\Models\BusinessDocument;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

it('lets an admin fetch a short-TTL signed URL for a business verification document', function () {
    Storage::fake('s3');

    $admin = User::factory()->admin()->create();
    $admin->assignRole(RoleName::Admin->value);
    $token = $admin->createToken('device')->plainTextToken;

    $document = BusinessDocument::factory()->create(['file_path' => 'business-documents/some-business/license.pdf']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/admin/businesses/documents/{$document->id}/url")
        ->assertOk();

    expect($response->json('data.url'))->not->toBeEmpty()
        ->and($response->json('data.expires_at'))->not->toBeEmpty();
});

it('denies a non-admin from fetching a business document URL', function () {
    Storage::fake('s3');

    $provider = User::factory()->provider()->create();
    $provider->assignRole(RoleName::ProviderOwner->value);
    $token = $provider->createToken('device')->plainTextToken;

    $document = BusinessDocument::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/admin/businesses/documents/{$document->id}/url")
        ->assertForbidden();
});

it('rejects an unauthenticated request for a business document URL', function () {
    $document = BusinessDocument::factory()->create();

    $this->getJson("/api/v1/admin/businesses/documents/{$document->id}/url")
        ->assertUnauthorized();
});
