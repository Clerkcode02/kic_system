<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Support\Concerns\HasUuidv7;
use Database\Factories\PlatformSettingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    /** @use HasFactory<PlatformSettingFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * `value` is untyped text at the column level (type drives the read-side
     * cast below) — not declared in $casts because the target type is
     * per-row, not fixed per-column like a normal Eloquent cast.
     *
     * @return Attribute<string|int|float|bool|array<mixed>, never>
     */
    protected function typedValue(): Attribute
    {
        return Attribute::get(fn (): string|int|float|bool|array => match ($this->type) {
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        });
    }
}
