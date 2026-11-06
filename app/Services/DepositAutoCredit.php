<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Credits pending deposits on its own once Bybit shows the money arrived.
 *
 * A deposit is only credited when all of these hold, otherwise it is left
 * for an admin to review as before:
 *  - the owner switched auto-credit on in Site settings;
 *  - Bybit has a finished deposit with the same transaction ID, coin and amount;
 *  - Bybit received it at the address the user picked (when Bybit reports one);
 *  - we have a price for the coin (stablecoins count 1:1), and other coins
 *    only when the owner allowed crypto at the live price;
 *  - the USDT value is no more than the auto-credit limit.
 *
 * Transaction IDs are unique per deposit, so one payment can never be claimed twice.
 */
class DepositAutoCredit
{
    public const string NOTE = 'Credited automatically: Bybit confirmed the same transaction ID, coin and amount.';

    public function __construct(
        private BybitAccount $bybit,
        private MarketPrices $prices,
        private WalletService $wallet,
    ) {}

    public static function isEnabled(): bool
    {
        return Setting::enabled('auto_credit_deposits');
    }

    /**
     * Check every pending deposit with a transaction ID and credit the ones
     * that pass. Returns how many were credited and how many were left for review.
     *
     * @return array{credited: int, left: int}
     */
    public function run(): array
    {
        $counts = ['credited' => 0, 'left' => 0];

        if (! self::isEnabled() || ! $this->bybit->isConfigured()) {
            return $counts;
        }

        Cache::lock('deposit-auto-credit', 120)->get(function () use (&$counts): void {
            Deposit::query()
                ->with('wallet')
                ->where('status', RequestStatus::Pending)
                ->whereNotNull('reference')
                ->where('created_at', '>=', now()->subDays(30))
                ->oldest('id')
                ->each(function (Deposit $deposit) use (&$counts): void {
                    $credit = $this->creditFor($deposit);

                    if ($credit === null) {
                        $counts['left']++;

                        return;
                    }

                    try {
                        $this->wallet->approveDeposit($deposit, null, $credit, self::NOTE);
                        $counts['credited']++;
                    } catch (WalletException) {
                        $counts['left']++;
                    }
                });
        });

        return $counts;
    }

    /**
     * The USDT to credit for a deposit, or null when it must wait for an admin.
     */
    public function creditFor(Deposit $deposit): ?int
    {
        $check = $this->bybit->checkDeposit($deposit);

        if ($check === null || $check['state'] !== 'match') {
            return null;
        }

        $receivedAt = $check['bybit']['to_address'] ?? '';

        if ($receivedAt !== '' && $deposit->wallet !== null && strcasecmp($receivedAt, $deposit->wallet->address) !== 0) {
            return null;
        }

        $priceCents = DepositWallet::priceCentsFor($deposit->coin, $this->prices);
        $isStablecoin = $priceCents === 100 && in_array(strtoupper($deposit->coin), ['USDT', 'USDC'], true);

        if ($priceCents === null || (! $isStablecoin && ! Setting::enabled('auto_credit_crypto'))) {
            return null;
        }

        $credit = $deposit->estimatedMicros($this->prices);
        $limit = Setting::integer('auto_credit_max_micros');

        if ($credit === null || $credit <= 0 || ($limit > 0 && $credit > $limit)) {
            return null;
        }

        return $credit;
    }
}
