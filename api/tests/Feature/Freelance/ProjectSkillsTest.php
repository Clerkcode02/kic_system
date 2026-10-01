<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use App\Support\ValueObjects\SkillList;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// Local, uniquely-named helpers — CLAUDE.md §11 (a helper shared across test
// files must live in tests/Pest.php to survive --parallel).

function skillsClient(): User
{
    $user = User::factory()->customer()->create();
    $user->assignRole(RoleName::Customer->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function skillsProjectPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::factory()->create()->id,
        'title' => 'Build a marketing site',
        'description' => 'Full description of the work needed.',
        'budget_min' => '500.00',
        'budget_max' => '1000.00',
        'deadline' => now()->addMonth()->toDateString(),
    ], $overrides);
}

// --- Publishing with skills -------------------------------------------------

it('stores required skills normalized to lowercase, trimmed and deduped', function () {
    $client = skillsClient();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', skillsProjectPayload([
            'required_skills' => ['  React ', 'REACT', 'Machine   Learning', 'TypeScript'],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.required_skills', ['react', 'machine learning', 'typescript']);
});

it('returns an empty skills array when none were given', function () {
    $client = skillsClient();

    $response = $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', skillsProjectPayload())
        ->assertCreated()
        ->assertJsonPath('data.required_skills', []);

    // Stored as NULL rather than [] — "not specified" is a different
    // assertion from "zero skills required".
    expect(Project::findOrFail($response->json('data.id'))->getRawOriginal('required_skills'))->toBeNull();
});

it('rejects more skills than the cap', function () {
    $client = skillsClient();

    $tooMany = array_map(fn (int $i) => "skill-{$i}", range(1, SkillList::MAX_SKILLS + 1));

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', skillsProjectPayload(['required_skills' => $tooMany]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('required_skills');
});

it('rejects an over-long individual skill', function () {
    $client = skillsClient();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', skillsProjectPayload([
            'required_skills' => [str_repeat('a', SkillList::MAX_SKILL_LENGTH + 1)],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('required_skills.0');
});

it('rejects a non-array skills value', function () {
    $client = skillsClient();

    $this->withHeaders(authHeader($client))
        ->postJson('/api/v1/projects', skillsProjectPayload(['required_skills' => 'react']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('required_skills');
});

// --- Updating skills --------------------------------------------------------

it('replaces the skill list on update', function () {
    $client = skillsClient();
    $project = Project::factory()->open()->create([
        'client_id' => $client->id,
        'required_skills' => ['react'],
    ]);

    $this->withHeaders(authHeader($client))
        ->patchJson("/api/v1/projects/{$project->id}", ['required_skills' => ['Vue', 'nuxt']])
        ->assertOk()
        ->assertJsonPath('data.required_skills', ['vue', 'nuxt']);
});

it('clears the skill list when given an empty array', function () {
    $client = skillsClient();
    $project = Project::factory()->open()->create([
        'client_id' => $client->id,
        'required_skills' => ['react'],
    ]);

    $this->withHeaders(authHeader($client))
        ->patchJson("/api/v1/projects/{$project->id}", ['required_skills' => []])
        ->assertOk()
        ->assertJsonPath('data.required_skills', []);
});

it('leaves the skill list untouched when the key is absent', function () {
    $client = skillsClient();
    $project = Project::factory()->open()->create([
        'client_id' => $client->id,
        'required_skills' => ['react'],
    ]);

    $this->withHeaders(authHeader($client))
        ->patchJson("/api/v1/projects/{$project->id}", ['title' => 'A new title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'A new title')
        ->assertJsonPath('data.required_skills', ['react']);
});

// --- Filtering --------------------------------------------------------------

it('filters the public project list to any of the requested skills', function () {
    $react = Project::factory()->open()->create(['required_skills' => ['react', 'typescript']]);
    $vue = Project::factory()->open()->create(['required_skills' => ['vue']]);
    Project::factory()->open()->create(['required_skills' => ['rust']]);
    Project::factory()->open()->create(['required_skills' => null]);

    $response = $this->getJson('/api/v1/projects?skills[]=react&skills[]=vue')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toEqualCanonicalizing([$react->id, $vue->id]);
});

it('normalizes the filter input so casing and spacing still match', function () {
    $project = Project::factory()->open()->create(['required_skills' => ['machine learning']]);

    $this->getJson('/api/v1/projects?skills[]='.urlencode('  Machine   Learning '))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $project->id);
});

it('ignores an empty skills filter rather than matching nothing', function () {
    $project = Project::factory()->open()->create(['required_skills' => ['react']]);

    $this->getJson('/api/v1/projects?skills[]=')
        ->assertOk()
        ->assertJsonPath('data.0.id', $project->id);
});

it('combines the skills filter with the category filter', function () {
    $category = Category::factory()->create();
    $match = Project::factory()->open()->create([
        'category_id' => $category->id,
        'required_skills' => ['react'],
    ]);
    Project::factory()->open()->create(['required_skills' => ['react']]);

    $this->getJson("/api/v1/projects?category={$category->id}&skills[]=react")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('rejects a skills filter that exceeds the cap', function () {
    $tooMany = collect(range(1, SkillList::MAX_SKILLS + 1))
        ->map(fn (int $i) => "skills[]=skill-{$i}")
        ->implode('&');

    $this->getJson("/api/v1/projects?{$tooMany}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('skills');
});
