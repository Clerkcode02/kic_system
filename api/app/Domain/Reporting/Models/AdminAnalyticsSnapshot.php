<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Support\Concerns\HasUuidv7;
use Database\Factories\AdminAnalyticsSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAnalyticsSnapshot extends Model
{
    /** @use HasFactory<AdminAnalyticsSnapshotFactory> */
    use HasFactory;
    use HasUuidv7;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'snapshot_at',
        'metrics',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_at' => 'datetime',
            'metrics' => 'array',
        ];
    }
}
