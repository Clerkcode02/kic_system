<?php

declare(strict_types=1);

use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('s3');
});

// Local, uniquely-named helpers — CLAUDE.md §11.

function briefClient(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

function briefFreelancer(): User
{
    $user = User::factory()->freelancer()->create();
    $user->assignRole(RoleName::Freelancer->value);
    FreelancerProfile::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function presignPayload(Project $project): array
{
    return [
        'attachable_type' => 'project',
        'attachable_id' => $project->id,
        'filename' => 'brief.pdf',
    ];
}

it('lets the owning client presign a brief upload for their project', function () {
    $client = briefClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $response = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->assertOk();

    expect($response->json('data.path'))->toStartWith("attachments/project/{$project->id}/")
        ->and($response->json('data.url'))->not->toBeEmpty();
});

it('lets the client confirm an uploaded brief', function () {
    $client = briefClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $path = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->json('data.path');

    forgetAuthGuards();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/uploads/confirm', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
        ])
        ->assertCreated();

    expect($project->refresh()->attachments)->toHaveCount(1);
});

/*
 * The security boundary this whole change turns on. Browsing a project is
 * public, so without a narrower ability than `view`, any freelancer — or any
 * signed-in stranger — could attach files to someone else's project.
 */

it('denies a freelancer who merely proposed on the project', function () {
    $client = briefClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $competitor = briefFreelancer();
    Proposal::factory()->create([
        'project_id' => $project->id,
        'freelancer_id' => $competitor->freelancerProfile->id,
    ]);

    $this->withHeaders(authHeader($competitor))
        ->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->assertForbidden();
});

it('denies a signed-in stranger with no connection to the project', function () {
    $project = Project::factory()->open()->create(['client_id' => briefClient()->id]);

    $this->withHeaders(authHeader(briefClient()))
        ->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->assertForbidden();
});

it('allows the hired freelancer once a contract exists', function () {
    $client = briefClient();
    $hired = briefFreelancer();

    $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
    $proposal = Proposal::factory()->accepted()->create([
        'project_id' => $project->id,
        'freelancer_id' => $hired->freelancerProfile->id,
    ]);
    Contract::factory()->active()->create([
        'project_id' => $project->id,
        'proposal_id' => $proposal->id,
    ]);

    $this->withHeaders(authHeader($hired))
        ->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->assertOk();
});

it('denies confirming a brief against a project the caller does not own', function () {
    $client = briefClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $path = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->json('data.path');

    // The auth guard memoizes the user resolved by the presign call above;
    // without this the confirm call below is still authenticated as $client
    // and the ownership check never runs (see tests/Pest.php).
    forgetAuthGuards();

    $this->withHeaders(authHeader(briefClient()))
        ->postJson('/api/v1/uploads/confirm', [
            'attachable_type' => 'project',
            'attachable_id' => $project->id,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
        ])
        ->assertForbidden();
});

it('rejects an unauthenticated presign request for a project', function () {
    $project = Project::factory()->open()->create();

    $this->postJson('/api/v1/uploads/presign', presignPayload($project))
        ->assertUnauthorized();
});

it('still rejects an attachable type outside the allow-list', function () {
    $client = briefClient();
    $project = Project::factory()->open()->create(['client_id' => $client->id]);

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/uploads/presign', [
            'attachable_type' => 'contract',
            'attachable_id' => $project->id,
            'filename' => 'brief.pdf',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('attachable_type');
});
