<?php

namespace App\Models;

use App\Services\MarketPrices;
use Carbon\CarbonInterface;
use Database\Factories\DepositWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A company wallet users can send a deposit to, in any coin. The admin checks
 * each deposit on the blockchain and credits its value in USDT.
 *
 * @property int $id
 * @property string $coin
 * @property string $network
 * @property string $address
 * @property string|null $explorer_tx_url
 * @property string|null $qr_path
 * @property bool $is_active
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['coin', 'network', 'address', 'explorer_tx_url', 'qr_path', 'is_active', 'sort_order'])]
class DepositWallet extends Model
{
    /** @use HasFactory<DepositWalletFactory> */
    use HasFactory;

    /**
     * Coins worth one US dollar each.
     */
    private const array STABLECOINS = ['USDT', 'USDC'];

    /**
     * Coins with a live price, mapped to the MarketPrices key.
     */
    private const array PRICED_COINS = ['BTC' => 'btc', 'ETH' => 'eth', 'SOL' => 'sol'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Deposit, $this>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /**
     * Wallets users can choose, in the admin's order.
     *
     * @param  Builder<DepositWallet>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The public URL of the QR code image the admin uploaded, if any.
     */
    public function qrUrl(): ?string
    {
        return $this->qr_path ? Storage::disk('public')->url($this->qr_path) : null;
    }

    /**
     * e.g. "USDT · TRC20".
     */
    public function label(): string
    {
        return $this->coin.' · '.$this->network;
    }

    /**
     * The US dollar price of one coin in cents, or null when we have no live price for it.
     */
    public static function priceCentsFor(string $coin, MarketPrices $prices): ?int
    {
        $coin = strtoupper($coin);

        if (in_array($coin, self::STABLECOINS, true)) {
            return 100;
        }

        $key = self::PRICED_COINS[$coin] ?? null;

        return $key === null ? null : $prices->cached()[$key.'_cents'];
    }
}
