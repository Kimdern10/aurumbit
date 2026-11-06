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
 * @property int $amount_micros
 * @property int $balance_after_micros
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $actor_id
 * @property CarbonInterface $created_at
 * @property-read User $user
 * @property-read User|null $actor
 */
#[Fillable(['user_id', 'type', 'amount_micros', 'balance_after_micros', 'description', 'subject_type', 'subject_id', 'actor_id'])]
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
            'amount_micros' => 'integer',
            'balance_after_micros' => 'integer',
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
     * The name customers see for this change. Admin balance adjustments read
     * as "Wallet funded" or "Wallet debited" rather than "Balance adjustment".
     */
    public function label(): string
    {
        if ($this->type === LedgerType::Adjustment) {
            return $this->amount_micros >= 0 ? 'Wallet funded' : 'Wallet debited';
        }

        return $this->type->label();
    }

    /**
     * The receipt of the deposit or withdrawal behind this change, if there is one.
     */
    public function receiptUrl(): ?string
    {
        return match ($this->subject_type) {
            (new Deposit)->getMorphClass() => route('receipts.show', ['kind' => 'deposit', 'id' => $this->subject_id]),
            (new Withdrawal)->getMorphClass() => route('receipts.show', ['kind' => 'withdrawal', 'id' => $this->subject_id]),
            default => null,
        };
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
