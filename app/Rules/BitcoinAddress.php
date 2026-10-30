<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts a Bitcoin mainnet address and checks its checksum, so a mistyped
 * character is caught: legacy (1…), script (3…), SegWit (bc1q…) and Taproot (bc1p…).
 */
class BitcoinAddress implements ValidationRule
{
    private const string BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    private const string BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    private const int BECH32_CONST = 1;

    private const int BECH32M_CONST = 0x2BC830A3;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('Enter a valid Bitcoin address (starting with 1, 3 or bc1). Check every character.');
        }
    }

    public static function isValid(string $address): bool
    {
        $address = trim($address);

        if (str_starts_with(strtolower($address), 'bc1')) {
            return self::isValidBech32($address);
        }

        return self::isValidBase58($address);
    }

    private static function isValidBase58(string $address): bool
    {
        if (strlen($address) < 26 || strlen($address) > 35 || ! in_array($address[0], ['1', '3'], true)) {
            return false;
        }

        // Base58 to bytes, little-endian while building, without big-number maths.
        $bytes = [];

        foreach (str_split($address) as $char) {
            $carry = strpos(self::BASE58_ALPHABET, $char);

            if ($carry === false) {
                return false;
            }

            foreach ($bytes as $i => $byte) {
                $carry += $byte * 58;
                $bytes[$i] = $carry & 0xFF;
                $carry >>= 8;
            }

            while ($carry > 0) {
                $bytes[] = $carry & 0xFF;
                $carry >>= 8;
            }
        }

        $leadingZeros = strspn($address, '1');
        $decoded = str_repeat("\0", $leadingZeros).implode('', array_map('chr', array_reverse($bytes)));

        if (strlen($decoded) !== 25 || ! in_array(ord($decoded[0]), [0x00, 0x05], true)) {
            return false;
        }

        $payload = substr($decoded, 0, 21);
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return hash_equals($checksum, substr($decoded, 21));
    }

    private static function isValidBech32(string $address): bool
    {
        if (strlen($address) > 90 || (strtolower($address) !== $address && strtoupper($address) !== $address)) {
            return false;
        }

        $address = strtolower($address);
        $separator = strrpos($address, '1');

        if ($separator !== 2 || strlen($address) - $separator - 1 < 7) {
            return false;
        }

        $data = [];

        foreach (str_split(substr($address, 3)) as $char) {
            $value = strpos(self::BECH32_CHARSET, $char);

            if ($value === false) {
                return false;
            }

            $data[] = $value;
        }

        $polymod = self::polymod([...self::expandHrp('bc'), ...$data]);
        $version = $data[0];

        if ($version > 16 || $polymod !== ($version === 0 ? self::BECH32_CONST : self::BECH32M_CONST)) {
            return false;
        }

        $program = self::convertBits(array_slice($data, 1, -6));

        if ($program === null || count($program) < 2 || count($program) > 40) {
            return false;
        }

        return $version !== 0 || in_array(count($program), [20, 32], true);
    }

    /**
     * @return list<int>
     */
    private static function expandHrp(string $hrp): array
    {
        $high = $low = [];

        foreach (str_split($hrp) as $char) {
            $high[] = ord($char) >> 5;
            $low[] = ord($char) & 31;
        }

        return [...$high, 0, ...$low];
    }

    /**
     * @param  list<int>  $values
     */
    private static function polymod(array $values): int
    {
        $generator = [0x3B6A57B2, 0x26508E6D, 0x1EA119FA, 0x3D4233DD, 0x2A1462B3];
        $checksum = 1;

        foreach ($values as $value) {
            $top = $checksum >> 25;
            $checksum = (($checksum & 0x1FFFFFF) << 5) ^ $value;

            for ($i = 0; $i < 5; $i++) {
                if ((($top >> $i) & 1) === 1) {
                    $checksum ^= $generator[$i];
                }
            }
        }

        return $checksum;
    }

    /**
     * Regroup 5-bit values into bytes, rejecting non-zero padding.
     *
     * @param  list<int>  $data
     * @return list<int>|null
     */
    private static function convertBits(array $data): ?array
    {
        $accumulator = 0;
        $bits = 0;
        $result = [];

        foreach ($data as $value) {
            $accumulator = ($accumulator << 5) | $value;
            $bits += 5;

            while ($bits >= 8) {
                $bits -= 8;
                $result[] = ($accumulator >> $bits) & 0xFF;
            }
        }

        if ($bits >= 5 || (($accumulator << (8 - $bits)) & 0xFF) !== 0) {
            return null;
        }

        return $result;
    }
}
