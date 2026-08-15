<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Resolves a `Relation::enforceMorphMap()` alias (e.g. "dispute") plus a
 * primary key into the underlying model, for endpoints that accept a
 * polymorphic {type, id} pair from the client (uploads, disputes).
 */
final class MorphResolver
{
    public static function resolve(string $morphAlias, string $id): ?Model
    {
        /** @var class-string<Model>|null $class */
        $class = Relation::getMorphedModel($morphAlias);

        if ($class === null) {
            return null;
        }

        return $class::query()->find($id);
    }
}
