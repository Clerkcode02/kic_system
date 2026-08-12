# Validation Rules

Source: SRS §19. Each rule is enforced twice — in the StateMachine/Action (server-side source of truth) and mirrored in a FormRequest for a field-level 422. See `checklists.md` before shipping a rule change.

---

## 19. Validation Rules Summary

| Module | Rule |
|---|---|
| Registration | Unique email/phone/business registration number; verification required before transacting |
| Booking | No past bookings; no double-booking same provider/slot; within provider availability; blocked if provider suspended or at daily booking cap; must reference an active service |
| Quotation | Configurable expiry with pre-expiry reminders; provider reminded/booking auto-expires if no quote sent in time; rejection allows revise-or-cancel |
| Cancellation | Free cancel pre-acceptance; fee per policy post-acceptance; provider must supply reason; repeated provider cancellations flagged and factored into rating |
| Payment | Must succeed before confirmation; idempotent (no duplicate charge per booking/milestone); payouts blocked until completion/approval; every transaction audit-logged |
| Freelance | One active hire per project (exclusive); no payment release pre-approval; scope edits after proposals trigger applicant notification; one proposal per freelancer per project; no deliverables after cancellation; budget > 0; deadline in future; milestone amounts sum to project budget |
| Review | Only after completion; one per completed transaction; no self-review; edit window configurable, then locked |
| Admin | Only verified providers accept bookings; only approved freelancers receive projects; suspended accounts blocked at auth layer; every critical action audit-logged |

All rules are enforced in **both** the relevant StateMachine/Action class (server-side source of truth) and mirrored as **FormRequest** validation for fast, friendly `422` responses — never a raw 500 for a business-rule violation.

