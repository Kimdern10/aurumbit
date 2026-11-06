<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\WithdrawalNetwork;
use App\Support\Usdt;
use Carbon\CarbonInterface;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A withdrawal request. The amount is held from the balance when requested and
 * returned if the admin rejects it. The admin pays it from the company wallet
 * and records the transaction ID. The USDT amount is held from the balance;
 * it is paid in USDT or, converted at the price when asked, in BTC, ETH or SOL.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount_micros
 * @property WithdrawalNetwork $network
 * @property string $coin
 * @property string|null $coin_amount
 * @property int|null $price_cents
 * @property string $address
 * @property string|null $payout_txid
 * @property RequestStatus $status
 * @property string|null $admin_note
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'amount_micros', 'network', 'coin', 'coin_amount', 'price_cents', 'address'])]
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
            'amount_micros' => 'integer',
            'network' => WithdrawalNetwork::class,
            'price_cents' => 'integer',
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

    /**
     * Our own reference for this withdrawal, e.g. "WD-00012", for users and support to quote.
     * Created automatically; the blockchain transaction ID is separate and optional.
     */
    public function code(): string
    {
        return 'WD-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The printable receipt for this withdrawal.
     */
    public function receiptUrl(bool $forAdmin = false): string
    {
        return route($forAdmin ? 'admin.receipts.show' : 'receipts.show', ['kind' => 'withdrawal', 'id' => $this->id]);
    }

    /**
     * A link to the payment on a block explorer, once it has been paid.
     */
    public function payoutUrl(): ?string
    {
        return $this->payout_txid ? $this->network->explorerTxUrl().$this->payout_txid : null;
    }

    /**
     * What the user receives, e.g. "0.01300000 BTC" or "1,000.00 USDT".
     */
    public function payoutLabel(): string
    {
        if ($this->coin === 'USDT') {
            return Usdt::format($this->amount_micros).' USDT';
        }

        // Withdrawals made before balances switched to USDT have no coin amount on record.
        return $this->coin_amount !== null
            ? $this->coin_amount.' '.$this->coin
            : Usdt::format($this->amount_micros).' USDT '.__('in :coin', ['coin' => $this->coin]);
    }
}
