<?php

namespace App\Support;

/**
 * Converts between satoshis (integers, how amounts are stored) and the BTC
 * text people type and read. Works on strings so no float rounding happens.
 */
class Btc
{
    public const int SATS_PER_BTC = 100_000_000;

    /**
     * A BTC amount typed by a person: whole number with up to 8 decimals.
     */
    public const string INPUT_PATTERN = '/^\d{1,8}(\.\d{1,8})?$/';

    /**
     * Turn satoshis into BTC text, e.g. 1250000 → "0.0125".
     *
     * Trailing zeros are trimmed unless $fixed is true.
     */
    public static function format(int $sats, bool $fixed = false): string
    {
        $sign = $sats < 0 ? '-' : '';
        $sats = abs($sats);

        $whole = intdiv($sats, self::SATS_PER_BTC);
        $fraction = str_pad((string) ($sats % self::SATS_PER_BTC), 8, '0', STR_PAD_LEFT);

        if (! $fixed) {
            $fraction = rtrim($fraction, '0');
        }

        return $sign.number_format($whole).($fraction !== '' ? '.'.$fraction : '');
    }

    /**
     * Turn BTC text into satoshis, or null when it is not a valid amount.
     */
    public static function toSats(string $btc): ?int
    {
        $btc = trim($btc);

        if (preg_match(self::INPUT_PATTERN, $btc) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $btc, 2), 2, '');

        return (int) $whole * self::SATS_PER_BTC + (int) str_pad($fraction, 8, '0');
    }

    /**
     * The US dollar value of an amount at a price in cents, in whole cents.
     */
    public static function usdCents(int $sats, int $btcPriceCents): int
    {
        return intdiv($sats * $btcPriceCents, self::SATS_PER_BTC);
    }

    /**
     * Format cents as dollars, e.g. 6512345 → "$65,123.45".
     */
    public static function usd(int $cents): string
    {
        return '$'.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
