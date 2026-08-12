<?php

declare(strict_types=1);

namespace App\Domain\Business\Models;

use App\Domain\Business\Enums\BusinessDocumentType;
use App\Domain\Business\Enums\BusinessVerificationStatus;
use App\Support\Concerns\HasUuidv7;
use Database\Factories\BusinessDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocument extends Model
{
    /** @use HasFactory<BusinessDocumentFactory> */
    use HasFactory;
    use HasUuidv7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'document_type',
        'document_number',
        'file_path',
        'issuing_authority',
        'issued_at',
        'expires_at',
        'verification_status',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => BusinessDocumentType::class,
            'document_number' => 'encrypted',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verification_status' => BusinessVerificationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
