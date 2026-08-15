<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Actions;

use App\Domain\Booking\Models\Booking;
use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\Dispute\Events\DisputeRaised;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Freelance\Models\Deliverable;
use App\Domain\Freelance\Models\Milestone;
use App\Domain\Freelance\Models\Project;
use App\Domain\User\Models\User;
use App\Support\Action;
use App\Support\ConflictException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS §13: raising a dispute over a booking or freelance transaction.
 * `disputable_type` is a registered morph-map alias (booking|project|
 * milestone|deliverable — see AppServiceProvider::enforceMorphMap), never
 * a raw class name. Only a party to the underlying transaction may raise
 * one (mirrors DisputePolicy::isPartyToDisputable), and only one open
 * dispute is allowed per entity at a time — matching the guard already
 * used by RecordChargeDispute for Stripe-originated chargebacks.
 */
final class RaiseDispute implements Action
{
    public function handle(string $disputableType, string $disputableId, User $actor, string $reason): Dispute
    {
        $modelClass = Relation::getMorphedModel($disputableType);

        if ($modelClass === null) {
            throw ValidationException::withMessages([
                'disputable_type' => 'This entity type cannot be disputed.',
            ]);
        }

        /** @var Model|null $disputable */
        $disputable = $modelClass::query()->find($disputableId);

        if ($disputable === null) {
            throw ValidationException::withMessages([
                'disputable_id' => 'The disputed entity was not found.',
            ]);
        }

        if (! $this->isPartyTo($disputable, $actor)) {
            throw new ConflictException(
                'Only a party to this transaction may raise a dispute on it.',
                'not_a_party_to_disputable',
            );
        }

        return DB::transaction(function () use ($disputableType, $disputableId, $actor, $reason) {
            $alreadyOpen = Dispute::query()
                ->where('disputable_type', $disputableType)
                ->where('disputable_id', $disputableId)
                ->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview])
                ->exists();

            if ($alreadyOpen) {
                throw new ConflictException(
                    'A dispute is already open for this entity.',
                    'dispute_already_open',
                );
            }

            $dispute = Dispute::create([
                'disputable_type' => $disputableType,
                'disputable_id' => $disputableId,
                'raised_by' => $actor->id,
                'status' => DisputeStatus::Open,
                'resolution_notes' => $reason,
            ]);

            DisputeRaised::dispatch($dispute, $actor);

            return $dispute;
        });
    }

    private function isPartyTo(Model $disputable, User $user): bool
    {
        return match (true) {
            $disputable instanceof Booking => $disputable->customer_id === $user->id || $disputable->provider->user_id === $user->id,
            $disputable instanceof Project => $disputable->client_id === $user->id,
            $disputable instanceof Milestone => $disputable->contract->project->client_id === $user->id
                || $disputable->contract->proposal->freelancer->user_id === $user->id,
            $disputable instanceof Deliverable => $disputable->milestone->contract->project->client_id === $user->id
                || $disputable->milestone->contract->proposal->freelancer->user_id === $user->id,
            default => false,
        };
    }
}
