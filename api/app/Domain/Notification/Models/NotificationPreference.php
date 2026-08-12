<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'category',
        'email',
        'sms',
        'push_web',
        'push_mobile',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => 'boolean',
            'sms' => 'boolean',
            'push_web' => 'boolean',
            'push_mobile' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
