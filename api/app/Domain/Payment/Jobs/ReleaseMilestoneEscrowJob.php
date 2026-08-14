<?php

declare(strict_types=1);

namespace App\Domain\Payment\Jobs;

use App\Domain\Freelance\Models\Milestone;
use App\Domain\Payment\Actions\ReleaseMilestoneEscrow;
use App\Domain\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by ApproveMilestone. Runs on the `payments` queue (CLAUDE.md
 * §8 — real money movement, same isolation as CapturePayment) rather than
 * `payouts`, which is reserved for the nightly provider-payout batch.
 */
final class ReleaseMilestoneEscrowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $milestoneId,
        public readonly ?string $actorId = null,
    ) {
        $this->onQueue('payments');
    }

    public function handle(ReleaseMilestoneEscrow $action): void
    {
        $milestone = Milestone::query()->find($this->milestoneId);

        if ($milestone === null) {
            return;
        }

        $actor = $this->actorId !== null ? User::query()->find($this->actorId) : null;

        $action->handle($milestone, $actor);
    }
}
