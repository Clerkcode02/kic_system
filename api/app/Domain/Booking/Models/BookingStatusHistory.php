<?php

declare(strict_types=1);

namespace App\Domain\Booking\Models;

use App\Domain\User\Models\User;
use App\Support\Concerns\HasUuidv7;
use App\Support\Concerns\Immutable;
use Database\Factories\BookingStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStatusHistory extends Model
{
    /** @use HasFactory<BookingStatusHistoryFactory> */
    use HasFactory;
    use HasUuidv7;
    use Immutable;

    public const UPDATED_AT = null;

    /**
     * Eloquent's default pluralizer treats "History" as ending in "y" and
     * produces "booking_status_histories"; the actual table (per the
     * migration) is singular.
     *
     * @var string
     */
    protected $table = 'booking_status_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'from_status',
        'to_status',
        'changed_by',
        'note',
    ];

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
