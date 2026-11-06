<?php

namespace App\Rules;

use App\Enums\WithdrawalNetwork;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts a payout address for the chosen network. Tron (TRC20) addresses have
 * their checksum checked, so a mistyped character is caught; so do Bitcoin ones.
 * Ethereum addresses are checked for their 0x + 40 hex shape, Solana ones decode to 32 bytes.
 */
class UsdtAddress implements ValidationRule
{
    private const string BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function __construct(private ?WithdrawalNetwork $network) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->network === null || ! is_string($value)) {
            return;
        }

        if (! self::isValid($value, $this->network)) {
            $fail('Enter a valid '.$this->network->shortLabel().' address. '.$this->network->addressHint());
        }
    }

    public static function isValid(string $address, WithdrawalNetwork $network): bool
    {
        $address = trim($address);

        return match ($network) {
            WithdrawalNetwork::Trc20 => self::isValidTron($address),
            WithdrawalNetwork::Erc20, WithdrawalNetwork::Ethereum => preg_match('/^0x[0-9a-fA-F]{40}$/', $address) === 1,
            WithdrawalNetwork::Bitcoin => BitcoinAddress::isValid($address),
            WithdrawalNetwork::Solana => self::isValidSolana($address),
        };
    }

    /**
     * A Tron address is Base58Check: 0x41 + 20 bytes + a 4-byte double-SHA256 checksum.
     */
    private static function isValidTron(string $address): bool
    {
        if (strlen($address) !== 34 || $address[0] !== 'T') {
            return false;
        }

        $decoded = self::base58Decode($address);

        if ($decoded === null || strlen($decoded) !== 25 || ord($decoded[0]) !== 0x41) {
            return false;
        }

        $checksum = substr(hash('sha256', hash('sha256', substr($decoded, 0, 21), true), true), 0, 4);

        return hash_equals($checksum, substr($decoded, 21));
    }

    /**
     * A Solana address is a 32-byte public key written in Base58 (no checksum).
     */
    private static function isValidSolana(string $address): bool
    {
        if (strlen($address) < 32 || strlen($address) > 44) {
            return false;
        }

        $decoded = self::base58Decode($address);

        return $decoded !== null && strlen($decoded) === 32;
    }

    /**
     * Base58 to bytes, little-endian while building, without big-number maths.
     */
    private static function base58Decode(string $text): ?string
    {
        $bytes = [];

        foreach (str_split($text) as $char) {
            $carry = strpos(self::BASE58_ALPHABET, $char);

            if ($carry === false) {
                return null;
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

        return str_repeat("\0", strspn($text, '1')).implode('', array_map('chr', array_reverse($bytes)));
    }
}
