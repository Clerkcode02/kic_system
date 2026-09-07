# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Four account types, Canada-wide, general launch with no initial vertical or regional niche:

- **Customer** — needs a home/local service done (booking a provider) and/or wants project-based freelance work delivered (hiring a freelancer). May start as a guest (no account) for bookings; account required for freelance hiring.
- **Provider (Business)** — a verified local service business that responds to booking requests with quotations and gets paid on acceptance.
- **Freelancer** — an approved freelancer who submits proposals on published projects and delivers milestone-based work from escrow.
- **Administrator** — platform operator who approves/rejects businesses and freelancers, manages categories and fees, resolves disputes, issues refunds, and has full audit visibility.

## Product Purpose

A two-sided marketplace that lets customers get local services done (book → quote → pay) and get freelance project work delivered (publish → hire → milestone escrow) — comparable in scope to Thumbtack/Angi/TaskRabbit (services) and Upwork (freelance), but as one platform instead of two. Success is a customer completing either transaction type without friction, and providers/freelancers getting paid reliably through the platform's quotation and escrow mechanics.

## Positioning

The mechanism a single-purpose competitor can't copy without becoming a different product: **one account, one trust/verification and payment layer, covering both "book a local service" and "hire a freelancer for a project."** Thumbtack/Angi/TaskRabbit only do bookings; Upwork only does freelance projects. KIC System runs both transaction models (quotation-and-payment for bookings, proposal-and-escrow for freelance) under the same platform, same admin verification pipeline, and same audit/payment infrastructure.

## Operating Context

- **Booking flow**: customer (or unauthenticated guest) requests a service at a date/time/address → provider sends a line-itemized quotation → customer pays on acceptance → scheduled → completed. Guest bookings are tracked and managed via an emailed access-token link, not login.
- **Freelance flow**: client (account required) publishes a project → freelancers submit proposals → client hires one → contract is broken into milestones → freelancer delivers, client approves, escrow releases per milestone.
- **Verification**: businesses and freelancers go through an admin approval queue before they can quote/propose.
- **Payments**: Stripe Connect; bookings pay the connected account directly at charge time, freelance milestones are held in platform-balance escrow and transferred only on approval.
- Location is Canada-only: Leaflet/OSM maps, PostGIS radius search, CAD currency, Canadian Stripe Connect accounts.

## Capabilities and Constraints

- Web only for now (Phase 1): Laravel 12 API + responsive React SPA, including the admin surface. No native mobile app yet (Phase 2, later, out of scope for current design work) — see CLAUDE.md §9 for mobile-readiness constraints that already shape the API.
- Booking requires no account (guest path, end to end including payment); freelance requires an account for every action.
- No live chat/WebSockets, no SMS/phone-OTP verification anywhere in the product.
- Money is always precise decimal (no floats); quotation/contract totals are always server-recomputed, never trusted from the client.
- Maps/geocoding are Leaflet + OpenStreetMap-derived tiles only — no Google Maps/Places ever.

## Brand Commitments

None yet. No product/company name beyond the working repo name "KIC System," no logo, tagline, or voice has been decided — future design work is free to propose these.

## Evidence on Hand

No real content, testimonials, case studies, press, or brand assets exist yet. The product has not launched; future design work must not fabricate customer names, review quotes, provider counts, or usage stats.

## Product Principles

1. **Two transaction models, one trust layer.** Booking (quote-and-pay) and freelance (propose-and-escrow) are different mechanics but share one verification pipeline, one payment/audit infrastructure, and one account system — design should reinforce that they're one platform, not two bolted-together products.
2. **Guest-to-booking is a first-class path.** A customer who never creates an account must be able to complete a full booking, including payment, without friction or a forced signup wall.
3. **Money and status changes must read as trustworthy.** Quotation totals, escrow state, and milestone/payout status are the moments users are most anxious about — clarity and precision here matter more than visual flourish.
4. **Canada-only, not a placeholder.** Don't design for multi-currency, multi-country address formats, or i18n abstractions — the constraint is real for the current phase.
5. **No invented proof.** Pre-launch product with no real testimonials, logos, or usage numbers — design must not fabricate social proof.

## Accessibility & Inclusion

No product-specific accessibility requirement has been established beyond standard web accessibility practice.
