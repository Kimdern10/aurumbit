<?php

namespace App\Support;

/**
 * Converts between micro-USDT (integers, how balances are stored) and the
 * USDT text people type and read. 1 USDT = 1,000,000 micros, the same six
 * decimals USDT uses on the blockchain. Works on strings so no float rounding happens.
 */
class Usdt
{
    public const int MICROS_PER_USDT = 1_000_000;

    /**
     * A USDT amount typed by a person: whole number with up to 2 decimals.
     */
    public const string INPUT_PATTERN = '/^\d{1,9}(\.\d{1,2})?$/';

    /**
     * Turn micros into USDT text, e.g. 1234560000 → "1,234.56".
     *
     * Rounded down to $decimals places so a balance is never shown higher than it is.
     */
    public static function format(int $micros, int $decimals = 2): string
    {
        $sign = $micros < 0 ? '-' : '';
        $micros = abs($micros);

        $whole = intdiv($micros, self::MICROS_PER_USDT);
        $fraction = substr(str_pad((string) ($micros % self::MICROS_PER_USDT), 6, '0', STR_PAD_LEFT), 0, $decimals);

        return $sign.number_format($whole).($decimals > 0 ? '.'.$fraction : '');
    }

    /**
     * A short form for tight spaces such as chart centres: "13,578.77" below a
     * million, then "713.06M" or "1.25B". Rounded down like format().
     */
    public static function compact(int $micros): string
    {
        $usdt = intdiv(abs($micros), self::MICROS_PER_USDT);
        $sign = $micros < 0 ? '-' : '';

        foreach ([1_000_000_000_000 => 'T', 1_000_000_000 => 'B', 1_000_000 => 'M'] as $size => $suffix) {
            if ($usdt >= $size) {
                return $sign.number_format(floor($usdt / $size * 100) / 100, 2).$suffix;
            }
        }

        return self::format($micros);
    }

    /**
     * Turn USDT text into micros, or null when it is not a valid amount.
     */
    public static function toMicros(string $usdt): ?int
    {
        $usdt = str_replace(',', '', trim($usdt));

        if (preg_match(self::INPUT_PATTERN, $usdt) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $usdt, 2), 2, '');

        return (int) $whole * self::MICROS_PER_USDT + (int) str_pad($fraction, 6, '0');
    }

    /**
     * The USDT value of a coin amount (text with up to 8 decimals) at a US dollar
     * price in cents, or null when the amount is not valid. Rounded down.
     */
    public static function fromCoin(string $coinAmount, int $priceCents): ?int
    {
        $units = Btc::toSats($coinAmount);

        return $units === null ? null : self::fromSats($units, $priceCents);
    }

    /**
     * The USDT value of satoshis at a BTC price in cents, rounded down.
     */
    public static function fromSats(int $sats, int $btcPriceCents): int
    {
        return intdiv($sats * $btcPriceCents, 10_000);
    }

    /**
     * How much of a coin (in 8-decimal units, like satoshis) an amount of USDT
     * buys at a US dollar price in cents, rounded down. Split in two steps so
     * large balances never overflow a 64-bit integer.
     */
    public static function toCoinUnits(int $micros, int $priceCents): int
    {
        return intdiv($micros, $priceCents) * 10_000 + intdiv(($micros % $priceCents) * 10_000, $priceCents);
    }

    /**
     * Micros as whole US cents, rounded down (USDT is valued at $1).
     */
    public static function toUsdCents(int $micros): int
    {
        return intdiv($micros, 10_000);
    }
}
