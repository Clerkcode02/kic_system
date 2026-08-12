---
name: laravel-domain-module
description: Use when creating a new domain module (e.g. Booking, Quotation, Project, Milestone, Dispute) in the KIC-System Laravel backend. Scaffolds the module using the project's Action/StateMachine/Policy/FormRequest pattern from the SRS. Trigger on requests like "add a new module for X", "scaffold the Booking domain", "create the Dispute entity".
---

# Laravel Domain Module Scaffolding

Every domain concept in this marketplace (Booking, Quotation, Project, Proposal, Milestone, Dispute, etc.) is built the same shape. Do not deviate from this structure — consistency here is what lets the codebase stay navigable as it grows past 30+ tables.

## Folder layout for a module named `{Entity}` (e.g. `Booking`)

```
app/
  Models/{Entity}.php
  Actions/{Entity}/
    Create{Entity}Action.php
    Update{Entity}Action.php
    Cancel{Entity}Action.php        # only if the entity has a cancellation flow
  StateMachines/{Entity}StateMachine.php   # only if the entity has states (Booking, Project, Dispute do; simple lookup tables don't)
  Policies/{Entity}Policy.php
  Http/
    Requests/{Entity}/
      Store{Entity}Request.php
      Update{Entity}Request.php
    Resources/{Entity}Resource.php
    Controllers/Api/{Entity}Controller.php
database/
  migrations/..._create_{entities}_table.php
  factories/{Entity}Factory.php
  seeders/{Entity}Seeder.php          # only if useful for local dev/demo data
tests/Feature/{Entity}/
  {Entity}CrudTest.php
  {Entity}AuthorizationTest.php       # 401/403 matrix
  {Entity}StateTransitionTest.php     # only if it has a state machine
```

## Rules, in order of importance

1. **No business logic in controllers.** Controllers call one Action class and return a Resource. If a controller method is more than ~10 lines excluding validation/authorization boilerplate, logic belongs in the Action.
2. **Every state-bearing entity gets an explicit state machine class**, not a raw enum column mutated ad hoc. Transitions are named methods (`$machine->accept()`, `$machine->cancel()`) that validate the current state before mutating, and throw a typed exception (e.g. `InvalidStateTransitionException`) on an illegal transition. Never let a controller or Action set the status column directly.
3. **Authorization lives in a Policy, never inline in the controller** (`$this->authorize()` or route middleware calling the policy — not a hand-rolled `if ($user->id !== ...)` check buried in the Action).
4. **FormRequests validate and authorize input shape only.** Business-rule validation (e.g. "can't book a slot the provider hasn't published availability for") happens in the Action, because it usually needs a DB lookup the FormRequest shouldn't own.
5. **Concurrency-sensitive Actions use `lockForUpdate()`** inside a DB transaction — this matters most for booking double-booking prevention and freelance project exclusive-hire acceptance. Write the concurrency test in the same PR that creates the Action, not later.
6. **Every Action gets a matching Pest feature test** covering: happy path, 401 (unauthenticated), 403 (wrong role/not the owner), 422 (invalid input), and 409 (illegal state transition or conflict) where applicable. Use the `pest-feature-test` skill for the exact test shape.
7. **Money is always `decimal(12,2)`, never float**, in both migrations and model casts.
8. **UUIDv7 primary keys** — see the `migration-conventions` skill for the exact migration syntax.

## When the SRS is silent on a detail for this module

Ask the user rather than inventing a policy borrowed from a similar marketplace product (Thumbtack, Upwork, etc.). Cancellation fees, refund windows, and dispute resolution timelines are the most common places this happens — the SRS explicitly leaves several of these open (see `PROJECT_SETUP.md`'s gap list).
