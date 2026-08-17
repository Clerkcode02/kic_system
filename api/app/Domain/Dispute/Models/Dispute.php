<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Models;

use App\Domain\Dispute\Enums\DisputeStatus;
use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\DisputeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disputable_type',
        'disputable_id',
        'raised_by',
        'assigned_admin_id',
        'status',
        'resolution_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function disputable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }
}
