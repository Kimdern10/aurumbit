<?php

namespace App\Enums;

/**
 * The kinds of plan on offer. Every plan is funded from the user's BTC balance.
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
            self::Bitcoin => 'Bitcoin mining',
            self::Gold => 'Gold mining',
            self::Ethereum => 'Ethereum staking',
            self::Solana => 'Solana staking',
            self::CryptoIndex => 'Crypto index',
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
            self::Bitcoin => 'Earn from a share of Bitcoin mining hashrate.',
            self::Gold => 'Earn from gold mining operations, with the gold value of your plan shown at the start.',
            self::Ethereum => 'Earn from Ethereum network staking rewards.',
            self::Solana => 'Earn from Solana network staking rewards.',
            self::CryptoIndex => 'A spread across the largest crypto assets.',
        };
    }
}
