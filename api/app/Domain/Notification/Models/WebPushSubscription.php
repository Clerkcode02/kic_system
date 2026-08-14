<?php

declare(strict_types=1);

namespace App\Domain\Notification\Models;

use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\WebPushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPushSubscription extends Model
{
    /** @use HasFactory<WebPushSubscriptionFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * Eloquent's naming convention would default to "web_push_subscriptions"
     * (snake+plural of the class name), but the migration uses the shorter
     * "push_subscriptions" (CLAUDE.md §9 mobile-readiness rule 7 — this is
     * the table a future MobilePushChannel reuses, not one it duplicates).
     */
    protected $table = 'push_subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
