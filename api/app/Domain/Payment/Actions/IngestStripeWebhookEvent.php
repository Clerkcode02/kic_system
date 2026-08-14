<?php

declare(strict_types=1);

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\Models\StripeEvent;
use App\Support\Action;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Stripe\Event;

/**
 * First half of CLAUDE.md §7's webhook contract: "writes the raw event to
 * stripe_events (deduped by event.id) before dispatching a queued
 * processing job." `stripe_events.stripe_event_id` carries the DB-level
 * unique constraint, so a replayed delivery racing another worker still
 * can't insert twice — the loser here returns false and the caller skips
 * dispatching a second processing job for it.
 */
final class IngestStripeWebhookEvent implements Action
{
    public function handle(Event $event): bool
    {
        try {
            // A duplicate key error poisons the enclosing transaction on
            // Postgres (every later query in it fails with "current
            // transaction is aborted") unless it's isolated in its own
            // savepoint — DB::transaction() gives it one automatically when
            // nested inside another transaction (e.g. a test's
            // RefreshDatabase wrapper).
            DB::transaction(function () use ($event) {
                StripeEvent::create([
                    'stripe_event_id' => $event->id,
                    'type' => $event->type,
                    'payload' => $event->toArray(),
                ]);
            });

            return true;
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return false;
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique constraint')
            || str_contains(strtolower($e->getMessage()), 'duplicate key');
    }
}
