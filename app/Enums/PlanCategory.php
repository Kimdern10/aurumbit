<?php

namespace App\Enums;

/**
 * The groups plans are shown in. Every plan is funded from, and paid to, the
 * user's USDT balance; the user picks the coin they are paid in when they withdraw.
 */
enum PlanCategory: string
{
    case Usdt = 'usdt';
    case Bitcoin = 'bitcoin';
    case Gold = 'gold';
    case Ethereum = 'ethereum';
    case Solana = 'solana';
    case CryptoIndex = 'crypto_index';

    public function label(): string
    {
        return match ($this) {
            self::Usdt => 'USDT plans',
            self::Bitcoin => 'Bitcoin plans',
            self::Gold => 'Gold plans',
            self::Ethereum => 'Ethereum plans',
            self::Solana => 'Solana plans',
            self::CryptoIndex => 'Crypto Mix plans',
        };
    }

    /**
     * A short tag shown in round badges, e.g. "₿" or "Au".
     */
    public function symbol(): string
    {
        return match ($this) {
            self::Usdt => '₮',
            self::Bitcoin => '₿',
            self::Gold => 'Au',
            self::Ethereum => 'Ξ',
            self::Solana => 'S',
            self::CryptoIndex => 'IX',
        };
    }

    /**
     * The brand colour used for this category in charts and badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Usdt => '#26a17b',
            self::Bitcoin => '#f7931a',
            self::Gold => '#c9a227',
            self::Ethereum => '#627eea',
            self::Solana => '#14b8a6',
            self::CryptoIndex => '#8b5cf6',
        };
    }

    /**
     * The Flux badge colour for this category.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Usdt => 'emerald',
            self::Bitcoin => 'orange',
            self::Gold => 'yellow',
            self::Ethereum => 'indigo',
            self::Solana => 'teal',
            self::CryptoIndex => 'violet',
        };
    }

    /**
     * The heroicon shown for this category.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Usdt => 'banknotes',
            self::Bitcoin => 'currency-dollar',
            self::Gold => 'cube',
            self::Ethereum => 'bolt',
            self::Solana => 'sparkles',
            self::CryptoIndex => 'chart-pie',
        };
    }

    /**
     * The MarketPrices key of the asset this category tracks, e.g. "btc", or
     * null when it has no single price (USDT is always $1, the mix has many).
     */
    public function priceKey(): ?string
    {
        return match ($this) {
            self::Bitcoin => 'btc',
            self::Gold => 'gold',
            self::Ethereum => 'eth',
            self::Solana => 'sol',
            self::Usdt, self::CryptoIndex => null,
        };
    }

    /**
     * The ticker shown next to the live price, e.g. "BTC".
     */
    public function ticker(): string
    {
        return match ($this) {
            self::Usdt => 'USDT',
            self::Bitcoin => 'BTC',
            self::Gold => 'Gold (1 oz)',
            self::Ethereum => 'ETH',
            self::Solana => 'SOL',
            self::CryptoIndex => 'Mix',
        };
    }

    /**
     * The market pair the live price is quoted in, e.g. "BTC/USDT".
     */
    public function pair(): ?string
    {
        return match ($this) {
            self::Bitcoin => 'BTC/USDT',
            self::Gold => 'XAU/USDT',
            self::Ethereum => 'ETH/USDT',
            self::Solana => 'SOL/USDT',
            self::Usdt, self::CryptoIndex => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Usdt => 'Fixed-term plans in USDT with a target rate (not guaranteed).',
            self::Bitcoin => 'Fixed-term Bitcoin plans with a target rate (not guaranteed). Withdraw in BTC or USDT.',
            self::Gold => 'Fixed-term plans that show the gold value of your plan at today\'s price.',
            self::Ethereum => 'Fixed-term Ethereum plans with a target rate (not guaranteed). Withdraw in ETH or USDT.',
            self::Solana => 'Fixed-term Solana plans with a target rate (not guaranteed). Withdraw in SOL or USDT.',
            self::CryptoIndex => 'Fixed-term plans across the crypto range, paid in the coin you choose.',
        };
    }
}
