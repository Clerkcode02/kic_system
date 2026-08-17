<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use App\Support\Concerns\HasUuidv7;
use Database\Factories\FailedLoginAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per bad-credential login attempt (SRS §17). Append-only: no
 * `updated_at`, enforced at the DB layer by a trigger (see the
 * create_failed_login_attempts_table migration). Written from
 * LoginUser::handle via FailedLoginMonitor.
 */
class FailedLoginAttempt extends Model
{
    /** @use HasFactory<FailedLoginAttemptFactory> */
    use HasFactory;
    use HasUuidv7;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
    ];
}
