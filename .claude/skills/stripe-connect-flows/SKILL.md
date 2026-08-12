---
name: stripe-connect-flows
description: Use whenever writing or reviewing code that touches Stripe Connect, payment intents, escrow holds, payouts, or webhooks in the KIC-System backend. Trigger on requests like "handle the payment for X", "process the webhook", "set up the payout for the provider", "implement escrow for milestones".
---

# Stripe Connect Patterns for This Marketplace

Two distinct payment flows exist in this system — do not conflate them.

## 1. Bookings/Quotations — destination charge

Customer pays, funds go directly to the provider's connected account (minus platform fee), no manual capture step needed. Use `transfer_data[destination]` on the PaymentIntent:

```php
$paymentIntent = $stripe->paymentIntents->create([
    'amount' => $amountInCents,
    'currency' => 'usd',
    'customer' => $customer->stripe_id,
    'transfer_data' => ['destination' => $provider->stripe_connect_id],
    'application_fee_amount' => $platformFeeInCents,
]);
```

Funds settle to the provider's balance on Stripe's normal schedule — the platform never holds these funds.

## 2. Freelance projects/milestones — escrow hold

Funds sit on the **platform's** Stripe balance until milestone approval, then get transferred. This is NOT a destination charge — capture to the platform account first, transfer separately on approval:

```php
// On milestone funding — charge to platform balance, no destination
$paymentIntent = $stripe->paymentIntents->create([
    'amount' => $amountInCents,
    'currency' => 'usd',
    'customer' => $customer->stripe_id,
    // no transfer_data — funds land on platform balance
]);

// On milestone approval — separate transfer to the freelancer
$transfer = $stripe->transfers->create([
    'amount' => $amountInCents,
    'currency' => 'usd',
    'destination' => $freelancer->stripe_connect_id,
    'transfer_group' => "milestone_{$milestone->id}",
]);
```

**Flag before building further:** holding customer funds on the platform balance pending milestone approval may be regulated-adjacent (money transmission) in some jurisdictions. Confirm with legal/compliance before this goes live — this is a real risk, not just a code-style note.

## Webhook handling — idempotency is mandatory

Every webhook handler must be safe to call twice with the same event (Stripe retries on any non-2xx response, and duplicates happen). Use the event ID as an idempotency key:

```php
public function handle(WebhookEvent $event): void
{
    if (WebhookLog::where('stripe_event_id', $event->id)->exists()) {
        return; // already processed
    }

    DB::transaction(function () use ($event) {
        WebhookLog::create(['stripe_event_id' => $event->id, 'type' => $event->type]);
        // ... actual handling
    });
}
```

Never trust the webhook payload's amount/status for anything security-sensitive without re-fetching the object from Stripe's API when the action is high-stakes (large payout, dispute resolution) — payloads can theoretically be replayed or forged if signature verification is ever misconfigured. Always verify the webhook signature (`Stripe-Signature` header) before processing, using the endpoint's specific webhook secret.

## Chargebacks / disputes

A Stripe `charge.dispute.created` webhook should create or update a row in the internal `disputes` table — don't let Stripe disputes and the platform's own dispute-resolution flow (customer vs. provider disagreements) become two disconnected systems. Map Stripe's dispute status onto the internal table's state machine explicitly; don't assume the enums line up 1:1.

## Testing

Use Stripe's test-mode fixtures and the Stripe CLI (`stripe listen --forward-to`) for local webhook testing, or the Stripe MCP server to inspect real test-mode event shapes rather than guessing the payload structure from memory.
