# Backend Conventions

Source: SRS §3, §14, §15, §16, §17. The module tree is also available standalone at `../assets/laravel-module-tree.txt` and the scheduler snippet at `../assets/scheduler.php`.

**Contents:** Laravel module structure · StateMachine pattern · Queue jobs & priorities · Scheduled tasks · File upload strategy · Security practices

---

## 3. Laravel Module Structure

```
app/
├── Domain/
│   ├── User/
│   │   ├── Models/ (User, Address)
│   │   ├── Actions/ (RegisterCustomer, RegisterBusiness, RegisterFreelancer)
│   │   ├── Policies/
│   │   └── Events/ (UserRegistered, UserVerified)
│   ├── Business/
│   │   ├── Models/ (Business, BusinessDocument, ProviderAvailability)
│   │   ├── Actions/ (SubmitForVerification, ApproveBusiness, RejectBusiness)
│   │   └── Services/ (AvailabilityService)
│   ├── Freelance/
│   │   ├── Models/ (FreelancerProfile, PortfolioItem, Project, Proposal, Contract, Milestone, Deliverable)
│   │   ├── Actions/ (PublishProject, SubmitProposal, HireFreelancer, SubmitMilestone, ApproveMilestone)
│   │   └── StateMachines/ (ProjectStateMachine, MilestoneStateMachine)
│   ├── Catalog/
│   │   ├── Models/ (Category, Service, ServicePricingTier)
│   │   └── Actions/ (CreateCategory, PublishService)
│   ├── Booking/
│   │   ├── Models/ (Booking, BookingStatusHistory, BookingAttachment)
│   │   ├── Actions/ (CreateBookingRequest, TransitionBookingStatus, CancelBooking)
│   │   ├── StateMachines/ (BookingStateMachine.php)
│   │   └── Validators/ (BookingAvailabilityValidator, DoubleBookingValidator)
│   ├── Quotation/
│   │   ├── Models/ (Quotation, QuotationLineItem)
│   │   ├── Actions/ (SendQuotation, AcceptQuotation, RejectQuotation, ReviseQuotation)
│   │   └── Jobs/ (ExpireStaleQuotations)
│   ├── Payment/
│   │   ├── Models/ (Payment, Payout, Refund)
│   │   ├── Services/ (StripePaymentService, StripeConnectPayoutService, EscrowService)
│   │   ├── Actions/ (CapturePayment, IssueRefund, ReleaseMilestoneEscrow)
│   │   └── Webhooks/ (StripeWebhookController)
│   ├── Review/
│   │   ├── Models/ (Review)
│   │   └── Actions/ (SubmitReview, ReplyToReview)
│   ├── Notification/
│   │   ├── Notifications/ (BookingCreated, QuotationSent, PaymentSucceeded, …)
│   │   └── Channels/ (WebPushChannel, SmsChannel)
│   ├── Dispute/
│   │   ├── Models/ (Dispute)
│   │   └── Actions/ (RaiseDispute, ResolveDispute)
│   └── Audit/
│       ├── Models/ (AuditLog)
│       └── Listeners/ (RecordAuditEntry — subscribes to all domain events)
├── Http/
│   ├── Controllers/Api/{Customer,Provider,Freelancer,Admin}/...
│   ├── Requests/  (FormRequest per action, one class per validated intent)
│   ├── Resources/ (API Resources — one per read model)
│   └── Middleware/ (EnsureVerified, EnsureNotSuspended, RoleMiddleware)
├── Policies/
├── Providers/
└── Console/
    ├── Commands/ (ExpireQuotations, RunPayoutBatch, SendBookingReminders)
    └── Kernel.php (scheduler definitions)
```

**Key architectural pattern:** every state transition (Booking, Quotation, Project, Milestone) is routed through a dedicated **StateMachine class** that defines the allowed transition graph and rejects illegal transitions with a typed exception — this is what the validation rules in §13 map onto, and it's the single source of truth so the same rules can't drift between the API and, e.g., a queued job that also mutates status.


---

## 14. Queue Jobs

| Job | Trigger | Queue |
|---|---|---|
| `SendNotificationJob` (per channel) | Any domain event | `notifications` |
| `ProcessStripeWebhookJob` | Stripe webhook received | `payments` (high priority) |
| `CapturePaymentJob` | Quotation accepted / milestone approved | `payments` |
| `ReleaseMilestoneEscrowJob` | Milestone approved | `payments` |
| `RunProviderPayoutJob` | Nightly batch, per eligible provider | `payouts` |
| `ExpireStaleQuotationsJob` | Scheduled sweep | `default` |
| `ExpireUnquotedBookingsJob` | Scheduled sweep | `default` |
| `GenerateAdminAnalyticsSnapshotJob` | Hourly | `reporting` |
| `SendBookingReminderJob` | T-24h / T-2h before scheduled_date | `notifications` |
| `SyncBusinessRatingAverageJob` | New review submitted | `default` |

Queues split by priority/isolation so a burst of notification sends never delays payment capture. Redis-backed with Horizon for monitoring/retries; failed jobs go to `failed_jobs` with alerting on repeated failure.

---

## 15. Scheduled Tasks (Laravel Scheduler)

```php
// routes/console.php or app/Console/Kernel.php
Schedule::job(new ExpireStaleQuotationsJob)->everyFiveMinutes();
Schedule::job(new ExpireUnquotedBookingsJob)->everyFifteenMinutes();
Schedule::job(new SendBookingReminderJob)->hourly();
Schedule::job(new RunProviderPayoutJob)->dailyAt('02:00');
Schedule::job(new GenerateAdminAnalyticsSnapshotJob)->hourly();
Schedule::command('audit:archive-old-logs')->monthly();
Schedule::command('backup:run')->dailyAt('01:00');
```

All scheduled jobs use `->withoutOverlapping()` and `->onOneServer()` (cache-based lock) since the app runs on multiple app servers behind the load balancer.

---

## 16. File Upload Strategy

- Uploads (business documents, portfolio images, booking photos, deliverables) go directly to S3-compatible storage via **presigned URLs** generated by the API (`Storage::disk('s3')->temporaryUploadUrl()`), so large files never transit through the Laravel app server.
- File metadata (path, mime, size, owner, `scanned` flag) stored in a polymorphic `attachments` table.
- Virus/malware scan via a queued job (ClamAV or a cloud AV API) before a file is marked `available`; until scanned, files are not served to other users.
- Access control: private bucket by default; downloads proxied through a signed, short-TTL URL generated per-request and policy-checked (only the booking's customer/provider, or admins, can generate a URL for a booking attachment).
- Image variants (thumbnails for portfolio/service photos) generated async via a queued `GenerateImageVariantsJob` (Intervention Image), not on the request path.
- **[MOBILE ADDITION]** This flow is already client-agnostic and needs no change for mobile: the React Native app requests a presigned URL from the same endpoint and uploads directly to S3, exactly like the web app. Photos sourced from the device camera or photo library (booking photos, portfolio images, business documents) go through the identical presign → direct-upload → virus-scan → variant-generation pipeline.

---

## 17. Security Best Practices

- **Sanctum** + CSRF for SPA; rate limiting per route group (`throttle:auth`, `throttle:payments` tighter than general API).
- **Mass-assignment protection**: explicit `$fillable` per model, no `Model::unguard()`.
- **Authorization**: every controller action backed by a Policy — no ad-hoc `if ($user->id === ...)` scattered in controllers.
- **Input validation**: dedicated `FormRequest` per write endpoint; never trust client-computed totals (quotation totals, platform fees always recomputed server-side before persisting/charging).
- **Webhook security**: Stripe signature verification (`Stripe::verifyWebhookSignature`), replay protection via stored event IDs.
- **PII encryption at rest** for sensitive fields (government ID numbers on business documents) using Laravel's encrypted casts.
- **Secrets**: `.env` never committed; production secrets in a vault (AWS Secrets Manager / SSM Parameter Store), rotated.
- **SQL injection**: exclusively query builder / Eloquent, no raw string interpolation into queries.
- **XSS**: React escapes by default; API never returns raw HTML fields without sanitization (reviews, notes) — sanitize on write with a strict allow-list (or store as plain text only).
- **Audit + alerting**: anomaly alerts on repeated failed logins, unusual payout patterns, spike in refund rate.
- **Dependency hygiene**: `composer audit` / `npm audit` in CI, Dependabot enabled.

