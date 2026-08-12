<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Application-layer defence in depth for append-only tables. The
 * authoritative enforcement is the `prevent_update_delete` Postgres
 * trigger on the underlying table — this trait exists so a mistaken
 * `->update()`/`->delete()` call fails fast in PHP with a clear message
 * instead of surfacing as an opaque QueryException from the DB trigger.
 */
trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(function (Model $model) {
            throw new RuntimeException(static::class.' is append-only and cannot be updated.');
        });

        static::deleting(function (Model $model) {
            throw new RuntimeException(static::class.' is append-only and cannot be deleted.');
        });
    }
}
