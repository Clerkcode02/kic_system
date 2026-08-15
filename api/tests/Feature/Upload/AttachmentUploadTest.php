<?php

declare(strict_types=1);

use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Platform\Jobs\GenerateImageVariantsJob;
use App\Domain\Platform\Jobs\ScanAttachmentJob;
use App\Domain\Platform\Models\Attachment;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

/**
 * @return array{0: Dispute, 1: User, 2: User}
 */
function disputeWithParties(): array
{
    $client = User::factory()->customer()->create();
    $client->assignRole(RoleName::Customer->value);

    $freelancerUser = User::factory()->freelancer()->create();
    $freelancerUser->assignRole(RoleName::Freelancer->value);
    $freelancer = FreelancerProfile::factory()->approved()->create(['user_id' => $freelancerUser->id]);

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->accepted()->create(['project_id' => $project->id, 'freelancer_id' => $freelancer->id]);
    $contract = Contract::factory()->active()->create(['project_id' => $project->id, 'proposal_id' => $proposal->id]);
    $milestone = Milestone::factory()->approved()->create(['contract_id' => $contract->id]);

    $dispute = Dispute::create([
        'disputable_type' => 'milestone',
        'disputable_id' => $milestone->id,
        'raised_by' => $client->id,
        'status' => DisputeStatus::Open,
        'resolution_notes' => 'Work quality dispute.',
    ]);

    return [$dispute, $client, $freelancerUser];
}

it('requests a presigned upload URL scoped to the dispute for a party to it', function () {
    [$dispute, $client] = disputeWithParties();

    forgetAuthGuards();
    $token = $client->createToken('device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/uploads/presign', [
            'attachable_type' => 'dispute',
            'attachable_id' => $dispute->id,
            'filename' => 'evidence.png',
        ])
        ->assertOk();

    expect($response->json('data.path'))->toStartWith("attachments/dispute/{$dispute->id}/")
        ->and($response->json('data.url'))->not->toBeEmpty();
});

it('denies a stranger from requesting an upload URL for a dispute', function () {
    [$dispute] = disputeWithParties();
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);

    forgetAuthGuards();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/uploads/presign', [
            'attachable_type' => 'dispute',
            'attachable_id' => $dispute->id,
            'filename' => 'evidence.png',
        ])
        ->assertForbidden();
});

it('rejects an unauthenticated upload URL request', function () {
    $this->postJson('/api/v1/uploads/presign', [])->assertUnauthorized();
});

it('rejects an unsupported attachable type', function () {
    [$dispute, $client] = disputeWithParties();

    forgetAuthGuards();
    $token = $client->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/uploads/presign', [
            'attachable_type' => 'booking',
            'attachable_id' => $dispute->id,
            'filename' => 'evidence.png',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attachable_type']);
});

it('confirms an uploaded attachment and queues the virus scan', function () {
    Queue::fake();
    [$dispute, $client] = disputeWithParties();

    forgetAuthGuards();
    $token = $client->createToken('device')->plainTextToken;
    $path = "attachments/dispute/{$dispute->id}/".Str::uuid7().'.png';

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/uploads/confirm', [
            'attachable_type' => 'dispute',
            'attachable_id' => $dispute->id,
            'file_path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 12_345,
        ])
        ->assertCreated()
        ->assertJsonPath('data.scanned', false);

    Queue::assertPushed(ScanAttachmentJob::class);
    Queue::assertPushed(GenerateImageVariantsJob::class);
});

it('generates a thumbnail variant for an image attachment', function () {
    Storage::fake('s3');
    [$dispute, $client] = disputeWithParties();

    // A minimal valid 1x1 PNG, base64-encoded.
    $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    $path = "attachments/dispute/{$dispute->id}/".Str::uuid7().'.png';
    Storage::disk('s3')->put($path, $pngBinary);

    $attachment = Attachment::factory()->unscanned()->create([
        'attachable_type' => 'dispute',
        'attachable_id' => $dispute->id,
        'uploaded_by' => $client->id,
        'path' => $path,
        'mime_type' => 'image/png',
    ]);

    (new GenerateImageVariantsJob($attachment))->handle();

    $thumbnailPath = $attachment->fresh()->variants['thumbnail'] ?? null;

    expect($thumbnailPath)->not->toBeNull();
    Storage::disk('s3')->assertExists($thumbnailPath);
});

it('rejects confirming an attachment whose path does not belong to the dispute', function () {
    [$dispute, $client] = disputeWithParties();

    forgetAuthGuards();
    $token = $client->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/uploads/confirm', [
            'attachable_type' => 'dispute',
            'attachable_id' => $dispute->id,
            'file_path' => 'attachments/dispute/some-other-dispute/file.png',
            'mime_type' => 'image/png',
            'size_bytes' => 100,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_upload_path');
});

it('returns 404 for a download URL until the attachment has been scanned, then 200 after', function () {
    [$dispute, $client] = disputeWithParties();

    $attachment = Attachment::factory()->unscanned()->create([
        'attachable_type' => 'dispute',
        'attachable_id' => $dispute->id,
        'uploaded_by' => $client->id,
    ]);

    forgetAuthGuards();
    $token = $client->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/attachments/{$attachment->id}/url")
        ->assertNotFound();

    $attachment->update(['scanned' => true]);

    forgetAuthGuards();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/attachments/{$attachment->id}/url")
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'expires_at']]);
});

it('denies a stranger from generating a download URL for a dispute attachment', function () {
    [$dispute, $client] = disputeWithParties();
    $stranger = User::factory()->customer()->create();
    $stranger->assignRole(RoleName::Customer->value);

    $attachment = Attachment::factory()->create([
        'attachable_type' => 'dispute',
        'attachable_id' => $dispute->id,
        'uploaded_by' => $client->id,
    ]);

    forgetAuthGuards();
    $token = $stranger->createToken('device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/attachments/{$attachment->id}/url")
        ->assertForbidden();
});
