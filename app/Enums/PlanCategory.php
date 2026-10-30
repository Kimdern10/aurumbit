<?php

namespace App\Enums;

/**
 * The groups plans are shown in. Every plan is funded from, and paid to, the user's BTC balance.
 */
enum PlanCategory: string
{
    case Bitcoin = 'bitcoin';
    case Gold = 'gold';
    case Ethereum = 'ethereum';
    case Solana = 'solana';
    case CryptoIndex = 'crypto_index';

    public function label(): string
    {
        return match ($this) {
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
            self::Bitcoin => 'orange',
            self::Gold => 'yellow',
            self::Ethereum => 'indigo',
            self::Solana => 'teal',
            self::CryptoIndex => 'violet',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Bitcoin => 'Fixed-term plans with a set rate, paid in BTC.',
            self::Gold => 'Fixed-term plans that show the gold value of your plan at today\'s price.',
            self::Ethereum => 'Fixed-term Ethereum plans, funded and paid in BTC.',
            self::Solana => 'Fixed-term Solana plans, funded and paid in BTC.',
            self::CryptoIndex => 'Fixed-term plans across the crypto range, paid in BTC.',
        };
    }
}
