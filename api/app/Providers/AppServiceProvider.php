<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Events\BusinessSubmittedForVerification;
use App\Domain\Business\Listeners\NotifyAdminsOfVerificationSubmission;
use App\Domain\Business\Services\AvailabilityCache;
use App\Domain\Business\Services\GeoapifyGeocoder;
use App\Domain\Business\Services\Geocoder;
use App\Domain\Catalog\Events\CategoryTreeChanged;
use App\Domain\Catalog\Listeners\FlushCategoryTreeCache;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Events\UserRegistered;
use App\Domain\User\Listeners\SendEmailVerificationNotification;
use App\Domain\User\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            Geocoder::class,
            fn () => new GeoapifyGeocoder((string) config('services.geoapify.key'))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendEmailVerificationNotification::class);
        Event::listen(CategoryTreeChanged::class, FlushCategoryTreeCache::class);
        Event::listen(BusinessSubmittedForVerification::class, NotifyAdminsOfVerificationSubmission::class);

        // Provider availability is cached per-provider-per-day (5 min TTL);
        // any booking write for a date must bust that date's cache entry
        // immediately rather than waiting out the TTL (CLAUDE.md
        // provider-management spec). A plain model-event hook, not a domain
        // event/listener pair, since this is cache bookkeeping rather than a
        // business-rule side effect.
        Booking::created(function (Booking $booking) {
            $this->app->make(AvailabilityCache::class)->forget($booking->provider_id, $booking->scheduled_date->toDateString());
        });

        Booking::updated(function (Booking $booking) {
            if ($booking->wasChanged(['scheduled_date', 'status', 'time_slot_start', 'time_slot_end'])) {
                $this->app->make(AvailabilityCache::class)->forget($booking->provider_id, $booking->scheduled_date->toDateString());

                $originalDate = $booking->getOriginal('scheduled_date')?->toDateString();

                if ($originalDate !== null && $originalDate !== $booking->scheduled_date->toDateString()) {
                    $this->app->make(AvailabilityCache::class)->forget($booking->provider_id, $originalDate);
                }
            }
        });

        // Tighter than the general API — auth endpoints are the brute-force
        // surface (login, register). Keyed by IP + submitted email so one
        // abusive IP can't be sidestepped by rotating addresses.
        RateLimiter::for('auth', function (Request $request) {
            $identifier = (string) $request->input('email', '');

            return Limit::perMinute(10)->by($request->ip().'|'.$identifier);
        });

        // Short, stable aliases for polymorphic *_type columns (payable_type,
        // reviewable_type, disputable_type, attachable_type) instead of raw
        // FQCNs, matching the values documented in the SRS ERD. Strict mode
        // means every model touched by a morph relation must be listed here
        // — that includes User, whose Sanctum tokens() relation is a
        // morphMany, not just the domain models with explicit *_type columns.
        Relation::enforceMorphMap([
            'user' => User::class,
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
