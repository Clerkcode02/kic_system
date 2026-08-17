<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Models\AuditLog;
use App\Support\Auditable;
use App\Support\LabelsAuditActor;
use Illuminate\Http\Request;

/**
 * SRS §13: single global listener subscribed to every domain event
 * implementing the `Auditable` marker/contract. Registered as a wildcard
 * listener in AppServiceProvider so no per-event registration is needed —
 * a new critical event only has to implement `Auditable` to be captured.
 */
final class RecordAuditEntry
{
    public function __construct(private readonly Request $request)
    {
    }

    public function handle(Auditable $event): void
    {
        AuditLog::create([
            'actor_id' => $event->auditActorId(),
            // Guest actors have no users row to point actor_id at; those
            // events opt into a hashed label instead (SRS §13).
            'actor_label' => $event instanceof LabelsAuditActor ? $event->auditActorLabel() : null,
            'action' => $event->auditAction(),
            'auditable_type' => $event->auditableType(),
            'auditable_id' => $event->auditableId(),
            'before_state' => $event->auditBeforeState(),
            'after_state' => $event->auditAfterState(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
