<?php

declare(strict_types=1);

namespace App\Domain\Booking\Models;

use App\Support\Concerns\HasUuidv7;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SRS §6.1: the credential a guest presents as `X-Booking-Token`. Only the
 * sha256 hash of the plaintext is ever stored — see
 * {@see \App\Domain\Booking\Services\BookingAccessTokenService} for the
 * single place plaintext exists (creation, returned once).
 */
class BookingAccessToken extends Model
{
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'token_hash',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_ip',
    ];

    /**
     * The hash is a credential-equivalent: anyone holding it can't present
     * it directly, but it must never be serialized into a response or a log.
     *
     * @var list<string>
     */
    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @param  Builder<BookingAccessToken>  $query
     * @return Builder<BookingAccessToken>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
