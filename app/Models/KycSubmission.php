<?php

namespace App\Models;

use App\Enums\KycStatus;
use Carbon\CarbonInterface;
use Database\Factories\KycSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identity documents a user sent for review. Files live on the private disk.
 *
 * @property int $id
 * @property int $user_id
 * @property string $full_name
 * @property string $document_type
 * @property string $document_path
 * @property string $selfie_path
 * @property KycStatus $status
 * @property string|null $admin_note
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'full_name', 'document_type', 'document_path', 'selfie_path'])]
class KycSubmission extends Model
{
    /** @use HasFactory<KycSubmissionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    public const array DOCUMENT_TYPES = [
        'passport' => 'International passport',
        'national_id' => 'National ID card (NIN slip)',
        'drivers_licence' => "Driver's licence",
        'voters_card' => "Voter's card",
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KycStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentTypeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }
}
