<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Booking\Models\Booking;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short, stable aliases for polymorphic *_type columns (payable_type,
        // reviewable_type, disputable_type, attachable_type) instead of raw
        // FQCNs, matching the values documented in the SRS ERD.
        Relation::enforceMorphMap([
            'booking' => Booking::class,
            'project' => Project::class,
            'milestone' => Milestone::class,
            'deliverable' => Deliverable::class,
            'dispute' => Dispute::class,
        ]);

        // Models live under app/Domain/*/Models (CLAUDE.md §3), but factories
        // stay flat in database/factories/ — Laravel's default guesser mirrors
        // the model's namespace path, which doesn't exist there.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.Str::afterLast($modelName, '\\').'Factory'
        );
    }
}
