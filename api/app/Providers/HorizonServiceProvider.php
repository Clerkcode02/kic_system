<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\User\Enums\RoleName;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Admin is web-only per CLAUDE.md §4 — Horizon is an admin-tier
     * operational surface, gated by role rather than a hardcoded email list.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null) {
            return $user?->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value]) ?? false;
        });
    }
}
