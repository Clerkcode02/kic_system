<?php

declare(strict_types=1);

namespace App\Domain\Freelance\Models;

use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\DeliverableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deliverable extends Model
{
    /** @use HasFactory<DeliverableFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'milestone_id',
        'uploaded_by',
        'file_path',
        'mime_type',
        'size_bytes',
        'description',
        'submitted_at',
        'scanned',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'submitted_at' => 'datetime',
            'scanned' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
