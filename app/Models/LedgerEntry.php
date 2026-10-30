<?php

namespace App\Models;

use App\Enums\LedgerType;
use Carbon\CarbonInterface;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line in the audit log of balance changes. Rows are only ever inserted.
 *
 * @property int $id
 * @property int $user_id
 * @property LedgerType $type
 * @property int $amount_sats
 * @property int $balance_after_sats
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $actor_id
 * @property CarbonInterface $created_at
 * @property-read User $user
 * @property-read User|null $actor
 */
#[Fillable(['user_id', 'type', 'amount_sats', 'balance_after_sats', 'description', 'subject_type', 'subject_id', 'actor_id'])]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'amount_sats' => 'integer',
            'balance_after_sats' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who made the change, when it was not the user themselves.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
