<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use App\Support\Concerns\Immutable;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;
    use HasUuidv7;
    use Immutable;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'actor_label',
        'action',
        'auditable_type',
        'auditable_id',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
