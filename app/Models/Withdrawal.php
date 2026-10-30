<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Carbon\CarbonInterface;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A withdrawal request. The amount is held from the balance when requested and
 * returned if the admin rejects it. The admin pays it from the company wallet
 * and records the transaction ID.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount_sats
 * @property string $btc_address
 * @property string|null $payout_txid
 * @property RequestStatus $status
 * @property string|null $admin_note
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'amount_sats', 'btc_address'])]
class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_sats' => 'integer',
            'status' => RequestStatus::class,
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
}
