<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Forces time-ordered UUIDv7 primary keys. Laravel's HasUuids alone
 * generates v4 (random, not sortable) unless newUniqueId() is overridden.
 */
trait HasUuidv7
{
    use HasUuids;

    /**
     * Boot-time property overrides don't apply to traits, so this runs on
     * instantiation instead — Eloquent's default bigint auto-increment
     * assumptions must be off before any query builds on $this->getKeyName().
     */
    public function initializeHasUuidv7(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
}
