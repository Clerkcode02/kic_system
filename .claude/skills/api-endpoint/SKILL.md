---
name: api-endpoint
description: Use when adding a new API endpoint to the KIC-System Laravel backend — a single route/action, not a whole new domain module (use laravel-domain-module for that). Ensures every endpoint ships with authorization, validation, a resource transform, and a feature test as one unit. Trigger on requests like "add an endpoint for X", "expose Y over the API", "create a route to do Z".
---

# API Endpoint Checklist

An endpoint is not done until all five of these exist in the same commit. Never ship a route without all five — a route with no Policy check or no test is the most common source of production incidents in this codebase.

1. **Route** — registered in `routes/api.php`, grouped under the correct auth guard (see the dual Sanctum mode: stateful-cookie for the SPA origin, token (`Authorization: Bearer`) for everything else — never assume one or the other in the controller).
2. **FormRequest** — validates shape and calls `$this->authorize()` or defers to a Policy via route model binding. Rejects with 422 on invalid input, 403 on failed authorization — never let a raw validation exception leak a 500.
3. **Policy method** — the actual authorization decision (owner-only, role-only, or a combination) lives here, not in the controller or FormRequest.
4. **Resource class** — controls exactly what's serialized. Never return a raw Eloquent model from a controller. Be deliberate about which relations are eager-loaded to avoid N+1s — check with the Postgres MCP server's `EXPLAIN` if a list endpoint feels slow.
5. **Pest feature test** covering the full matrix: 200/201 happy path, 401 unauthenticated, 403 wrong role or not the resource owner, 422 invalid payload, and 409 if the endpoint can hit a state conflict (double-booking, already-accepted quotation, etc.). Use the `pest-feature-test` skill for the exact structure.

## Conventions specific to this API

- **All responses are JSON:API-ish but not strictly JSON:API** — follow whatever shape is already established in `OpenAPI.yaml`/`docs/openapi/` for consistency; don't invent a new envelope shape per endpoint.
- **Coordinates in, coordinates out.** Any endpoint accepting a location takes raw `lat`/`lng` floats — never a client-side-computed distance or a Google Maps place ID. Server computes distance via PostGIS `ST_DWithin`, never haversine in PHP.
- **Never trust a client-computed total.** Any endpoint touching money (booking totals, invoice amounts, payout amounts) recomputes the figure server-side from source rows; a client-supplied `total` field is ignored or used only as an idempotency check, never as the value written to the DB.
- **Update the OpenAPI spec in the same PR** that adds or changes an endpoint. This is the contract the future mobile client and generated TypeScript types depend on — drift here is the single highest-cost mistake to defer.
