<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Project;
use App\Support\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publicly lists only open projects via GET /api/v1/projects, without authentication', function () {
    $open = Project::factory()->open()->create();
    Project::factory()->inProgress()->create();
    Project::factory()->completed()->create();
    Project::factory()->cancelled()->create();

    $this->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);
});

it('filters projects by category', function () {
    $category = Category::factory()->create();
    $matching = Project::factory()->open()->create(['category_id' => $category->id]);
    Project::factory()->open()->create();

    $this->getJson("/api/v1/projects?category={$category->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id);
});

it('filters projects by budget range', function () {
    $inRange = Project::factory()->open()->create([
        'budget_min' => Money::fromDecimal('500.00', 'CAD'),
        'budget_max' => Money::fromDecimal('1500.00', 'CAD'),
    ]);
    $tooExpensive = Project::factory()->open()->create([
        'budget_min' => Money::fromDecimal('5000.00', 'CAD'),
        'budget_max' => Money::fromDecimal('8000.00', 'CAD'),
    ]);

    $this->getJson('/api/v1/projects?budget_min=400&budget_max=2000')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inRange->id);

    expect($tooExpensive)->not->toBeNull();
});

it('returns an empty list when no projects are open', function () {
    Project::factory()->completed()->create();

    $this->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows a single project with its category, client, and contract via GET /api/v1/projects/{project}', function () {
    $project = Project::factory()->inProgress()->create();
    $contract = Contract::factory()->create(['project_id' => $project->id]);

    $this->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.client.id', $project->client_id)
        ->assertJsonPath('data.category.id', $project->category_id)
        ->assertJsonPath('data.contract.id', $contract->id);
});

it('returns 404 for a project that does not exist', function () {
    $this->getJson('/api/v1/projects/0198f000-0000-7000-8000-000000000000')
        ->assertNotFound();
});
