<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Services\MarketPrices;
use App\Support\Usdt;
use Carbon\CarbonInterface;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deposit the user says they sent, in any coin the admin accepts. The admin
 * checks the transaction on the blockchain, then credits its value in USDT.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $deposit_wallet_id
 * @property string $coin
 * @property string|null $network
 * @property string $coin_amount
 * @property int|null $credited_micros
 * @property string|null $reference
 * @property RequestStatus $status
 * @property string|null $admin_note
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 * @property-read DepositWallet|null $wallet
 */
#[Fillable(['user_id', 'deposit_wallet_id', 'coin', 'network', 'coin_amount', 'reference'])]
class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
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
            'credited_micros' => 'integer',
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
     * @return BelongsTo<DepositWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(DepositWallet::class, 'deposit_wallet_id');
    }

    /**
     * Our own reference for this deposit, e.g. "DP-00012", for users and support to quote.
     */
    public function code(): string
    {
        return 'DP-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The printable receipt for this deposit.
     */
    public function receiptUrl(bool $forAdmin = false): string
    {
        return route($forAdmin ? 'admin.receipts.show' : 'receipts.show', ['kind' => 'deposit', 'id' => $this->id]);
    }

    /**
     * e.g. "0.5 ETH".
     */
    public function coinLabel(): string
    {
        return $this->coin_amount.' '.$this->coin;
    }

    /**
     * What the deposit is worth in USDT at today's price, or null when the
     * coin has no live price. Used to suggest the amount to credit.
     */
    public function estimatedMicros(MarketPrices $prices): ?int
    {
        $priceCents = DepositWallet::priceCentsFor($this->coin, $prices);

        return $priceCents === null ? null : Usdt::fromCoin($this->coin_amount, $priceCents);
    }

    /**
     * A link to the transaction on a block explorer, when the wallet has one set.
     */
    public function explorerUrl(): ?string
    {
        $base = $this->wallet?->explorer_tx_url;

        return filled($base) && filled($this->reference) ? $base.$this->reference : null;
    }
}
