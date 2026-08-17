<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Opt-in companion to {@see Auditable} for events whose actor may have no
 * `users` row — today, guest booking actors (SRS §6.1/§13).
 *
 * `audit_logs.actor_id` is a users FK, so a guest actor has nowhere to
 * land; `actor_label` carries `guest:<sha256 of normalized email>` instead
 * while `actor_id` stays null. A raw guest email must never reach the
 * trail.
 *
 * Events with only registered actors need not implement this — the
 * listener simply writes a null label.
 */
interface LabelsAuditActor
{
    /**
     * Null when the actor is a registered user (actor_id already
     * identifies them) or when the action is system-triggered.
     */
    public function auditActorLabel(): ?string;
}
