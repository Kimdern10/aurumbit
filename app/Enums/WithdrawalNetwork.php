<?php

namespace App\Enums;

/**
 * How a withdrawal is paid out: the coin and the network it is sent on.
 * Balances are kept in USDT; withdrawals in another coin are converted at the
 * live price when the user asks, and the admin sends that coin amount.
 */
enum WithdrawalNetwork: string
{
    case Trc20 = 'trc20';
    case Erc20 = 'erc20';
    case Bitcoin = 'bitcoin';
    case Ethereum = 'ethereum';
    case Solana = 'solana';

    /**
     * The payout options a user can pick for a new withdrawal.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Trc20, self::Erc20, self::Bitcoin, self::Ethereum, self::Solana];
    }

    /**
     * The coin that is sent, e.g. "BTC".
     */
    public function coin(): string
    {
        return match ($this) {
            self::Trc20, self::Erc20 => 'USDT',
            self::Bitcoin => 'BTC',
            self::Ethereum => 'ETH',
            self::Solana => 'SOL',
        };
    }

    /**
     * Whether the payout is USDT itself, so no conversion is needed.
     */
    public function isUsdt(): bool
    {
        return $this->coin() === 'USDT';
    }

    /**
     * The MarketPrices key used to convert USDT into this coin, or null for USDT.
     */
    public function priceKey(): ?string
    {
        return match ($this) {
            self::Trc20, self::Erc20 => null,
            self::Bitcoin => 'btc',
            self::Ethereum => 'eth',
            self::Solana => 'sol',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Trc20 => 'USDT · TRC20 (Tron)',
            self::Erc20 => 'USDT · ERC20 (Ethereum)',
            self::Bitcoin => 'BTC · Bitcoin',
            self::Ethereum => 'ETH · Ethereum',
            self::Solana => 'SOL · Solana',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Trc20 => 'USDT TRC20',
            self::Erc20 => 'USDT ERC20',
            self::Bitcoin => 'BTC',
            self::Ethereum => 'ETH',
            self::Solana => 'SOL',
        };
    }

    public function addressHint(): string
    {
        return match ($this) {
            self::Trc20 => 'Tron address starting with T, 34 characters.',
            self::Erc20, self::Ethereum => 'Ethereum address starting with 0x, 42 characters.',
            self::Bitcoin => 'Bitcoin address starting with 1, 3 or bc1.',
            self::Solana => 'Solana address, 32 to 44 letters and numbers.',
        };
    }

    public function addressPlaceholder(): string
    {
        return match ($this) {
            self::Trc20 => 'T…',
            self::Erc20, self::Ethereum => '0x…',
            self::Bitcoin => 'bc1q…',
            self::Solana => '7xKX…',
        };
    }

    /**
     * Where to look up a payment on this network, followed by the transaction ID.
     */
    public function explorerTxUrl(): string
    {
        return match ($this) {
            self::Trc20 => 'https://tronscan.org/#/transaction/',
            self::Erc20, self::Ethereum => 'https://etherscan.io/tx/',
            self::Bitcoin => 'https://mempool.space/tx/',
            self::Solana => 'https://solscan.io/tx/',
        };
    }

    /**
     * Whether a payment transaction ID has the right shape for this network.
     * Solana signatures are case-sensitive, so they are checked as typed.
     */
    public function isValidTxid(string $txid): bool
    {
        return match ($this) {
            self::Erc20, self::Ethereum => preg_match('/^0x[0-9a-f]{64}$/', $txid) === 1,
            self::Solana => preg_match('/^[1-9A-HJ-NP-Za-km-z]{86,88}$/', $txid) === 1,
            default => preg_match('/^[0-9a-f]{64}$/', $txid) === 1,
        };
    }

    /**
     * Normalise a typed transaction ID: hex IDs are lowercased, Solana's are kept as typed.
     */
    public function normalizeTxid(string $txid): string
    {
        $txid = trim($txid);

        return $this === self::Solana ? $txid : strtolower($txid);
    }

    public function txidHint(): string
    {
        return match ($this) {
            self::Erc20, self::Ethereum => 'Paste the transaction hash: 0x followed by 64 characters.',
            self::Solana => 'Paste the Solana transaction signature (87 or 88 characters).',
            default => 'Paste the 64-character transaction ID of the payment.',
        };
    }
}
