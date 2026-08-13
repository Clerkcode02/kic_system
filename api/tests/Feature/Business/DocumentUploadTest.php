<?php

declare(strict_types=1);

use App\Domain\Business\Models\Business;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);
});

/**
 * @return array{0: User, 1: Business, 2: string}
 */
function providerWithBusinessToken(): array
{
    $user = User::factory()->provider()->create();
    $user->assignRole(RoleName::ProviderOwner->value);
    $business = Business::factory()->pending()->create(['user_id' => $user->id]);
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $business, $token];
}

it('requests a presigned upload URL scoped to the business', function () {
    [, $business, $token] = providerWithBusinessToken();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/documents/upload-url', [
            'document_type' => 'business_license',
            'filename' => 'license.pdf',
        ])
        ->assertOk();

    $path = $response->json('data.path');

    expect($path)->toStartWith("business-documents/{$business->id}/")
        ->and($response->json('data.url'))->not->toBeEmpty();
});

it('confirms an uploaded document', function () {
    [, $business, $token] = providerWithBusinessToken();
    $path = "business-documents/{$business->id}/business_license-".Str::uuid7().'.pdf';

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/documents', [
            'file_path' => $path,
            'document_type' => 'business_license',
            'document_number' => 'BL-12345',
        ])
        ->assertCreated()
        ->assertJsonPath('data.document_type', 'business_license')
        ->assertJsonPath('data.verification_status', 'pending');
});

it('rejects confirming a document whose path does not belong to the business', function () {
    [, , $token] = providerWithBusinessToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/documents', [
            'file_path' => 'business-documents/some-other-business/file.pdf',
            'document_type' => 'business_license',
            'document_number' => 'BL-12345',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_upload_path');
});

it('rejects invalid document input', function () {
    [, , $token] = providerWithBusinessToken();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/documents', ['document_type' => 'not-a-real-type'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['document_type', 'document_number', 'file_path']);
});

it('denies a customer from requesting a document upload URL', function () {
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/provider/me/documents/upload-url', [
            'document_type' => 'business_license',
            'filename' => 'license.pdf',
        ])
        ->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    $this->postJson('/api/v1/provider/me/documents/upload-url', [])->assertUnauthorized();
});
