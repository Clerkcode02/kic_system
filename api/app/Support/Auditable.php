<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Marker interface for domain events that must be recorded to the audit
 * trail by the global `RecordAuditEntry` listener.
 */
interface Auditable
{
}
