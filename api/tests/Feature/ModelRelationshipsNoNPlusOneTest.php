<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAttachment;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessDocument;
use App\Domain\Business\Models\ProviderAvailability;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServicePricingTier;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Contract;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\FreelancerProfile;
use App\Domain\Freelance\Models\FreelancerSkill;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\PortfolioItem;
use App\Domain\Freelance\Models\Project;
use App\Domain\Freelance\Models\Proposal;
use App\Domain\Notification\Models\NotificationPreference;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Payout;
use App\Domain\Payment\Models\Refund;
use App\Domain\Payment\Models\StripeEvent;
use App\Domain\Platform\Models\Attachment;
use App\Domain\Platform\Models\IdempotencyKey;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Quotation\Models\Quotation;
use App\Domain\Quotation\Models\QuotationLineItem;
use App\Domain\Review\Models\Review;
use App\Domain\User\Models\Address;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => DB::enableQueryLog());

/**
 * Reflects on a model class and returns the names of its public,
 * zero-argument relationship methods (return type is a Relation subtype).
 *
 * @param  class-string  $modelClass
 * @return list<string>
 */
function relationMethodsOf(string $modelClass): array
{
    $reflection = new ReflectionClass($modelClass);
    $relations = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $modelClass) {
            continue;
        }

        if ($method->getNumberOfParameters() > 0 || $method->isStatic()) {
            continue;
        }

        // spatie/laravel-permission's HasRoles::teams() is a deliberate
        // `whereRaw('1 = 0')` no-op when teams are disabled (Config::teamsEnabled()
        // is false here — CLAUDE.md doesn't call for multi-tenant teams) — it
        // still builds its join against a `team_id` column that, correctly,
        // was never migrated. Postgres validates the join's columns before the
        // always-false WHERE short-circuits it, so touching this relation errors
        // regardless of eager-loading discipline. Not one of our relations to prove.
        if ($modelClass === App\Domain\User\Models\User::class && $method->getName() === 'teams') {
            continue;
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType
            && ! $returnType->isBuiltin()
            && is_subclass_of($returnType->getName(), Relation::class)
        ) {
            $relations[] = $method->getName();
        }
    }

    return $relations;
}

/**
 * @return int Number of DB queries the callback issued.
 */
function countQueriesDuring(Closure $callback): int
{
    DB::flushQueryLog();
    $callback();

    return count(DB::getQueryLog());
}

/**
 * Runs an eager-loaded fetch-and-touch-every-relation pass and returns the
 * query count. If any relation weren't actually eager-loaded, accessing it
 * on N rows would issue N additional lazy-load queries.
 *
 * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
 * @param  list<string>  $relations
 */
function queryCountForEagerLoadPass(string $modelClass, array $relations): int
{
    return countQueriesDuring(function () use ($modelClass, $relations) {
        $rows = $modelClass::query()->with($relations)->get();

        foreach ($rows as $row) {
            foreach ($relations as $relation) {
                // Touch only the top-level relation — nested dot-notation
                // (e.g. "quotations.lineItems") is eager-loaded as part of
                // the parent relation's query, not accessed separately here.
                $row->{explode('.', $relation)[0]};
            }
        }
    });
}

dataset('models_with_relations', [
    User::class,
    Address::class,
    Business::class,
    BusinessDocument::class,
    ProviderAvailability::class,
    Category::class,
    Service::class,
    ServicePricingTier::class,
    FreelancerProfile::class,
    FreelancerSkill::class,
    PortfolioItem::class,
    Project::class,
    Proposal::class,
    Contract::class,
    Milestone::class,
    Deliverable::class,
    Booking::class,
    BookingStatusHistory::class,
    BookingAttachment::class,
    Quotation::class,
    QuotationLineItem::class,
    Payment::class,
    Payout::class,
    Refund::class,
    Review::class,
    Dispute::class,
    NotificationPreference::class,
    AuditLog::class,
    Attachment::class,
    IdempotencyKey::class,
    PlatformSetting::class,
    StripeEvent::class,
]);

it('resolves every model relationship via eager loading without N+1 queries', function (string $modelClass) {
    $relations = relationMethodsOf($modelClass);

    if ($relations === []) {
        expect($relations)->toBe([]); // no relations declared — nothing to prove.

        return;
    }

    // Two independently-created rows...
    $modelClass::factory()->count(2)->create();
    $queriesFor2Rows = queryCountForEagerLoadPass($modelClass, $relations);

    // ...versus six. If any relation lazy-loads per row instead of being
    // batched by ->with(), this scales up instead of staying constant.
    $modelClass::factory()->count(4)->create();
    $queriesFor6Rows = queryCountForEagerLoadPass($modelClass, $relations);

    expect($queriesFor6Rows)
        ->toBe($queriesFor2Rows)
        ->and($queriesFor2Rows)
        ->toBeGreaterThan(0);
})->with('models_with_relations');
