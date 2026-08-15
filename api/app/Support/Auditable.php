<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contract for domain events recorded to the audit trail by the global
 * `RecordAuditEntry` listener (SRS §13). Every event implementing this
 * carries everything the listener needs to write one `audit_logs` row —
 * before/after are kept as small JSONB diffs, never full model snapshots
 * (CLAUDE.md §5 "Audit trail").
 */
interface Auditable
{
    /**
     * Null for system-triggered actions (scheduled sweeps, webhooks) with
     * no authenticated user to attribute the change to.
     */
    public function auditActorId(): ?string;

    /**
     * Dot-notation, e.g. `booking.status_changed`.
     */
    public function auditAction(): string;

    /**
     * Short polymorphic-map alias, e.g. `booking` — matches
     * `Relation::enforceMorphMap()` in AppServiceProvider.
     */
    public function auditableType(): string;

    public function auditableId(): string;

    /**
     * @return array<string, mixed>|null
     */
    public function auditBeforeState(): ?array;

    /**
     * @return array<string, mixed>|null
     */
    public function auditAfterState(): ?array;
}
