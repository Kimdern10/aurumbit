<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;

/**
 * Key/value store for the site settings the admin can change.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Values used until the admin saves their own.
     *
     * @var array<string, string>
     */
    public const array DEFAULTS = [
        'site_name' => 'AurumBit',
        'tagline' => 'Bitcoin and gold mining plans, explained clearly.',
        'support_email' => 'support@aurumbit.test',
        'seo_description' => 'AurumBit offers Bitcoin and gold mining investment plans with clear rates, terms, risk disclosures and a full audit trail of every balance change.',
        'deposit_address' => '',
        'explorer_tx_url' => 'https://mempool.space/tx/',
        'min_deposit_sats' => '100000',
        'min_withdrawal_sats' => '50000',
        'withdrawal_requires_kyc' => '1',
        'referral_bonus_bps' => '100',
        'company_details' => '',
    ];

    private const string CACHE_KEY = 'site-settings';

    /**
     * Get every stored value, falling back to the defaults. Read once per request.
     *
     * @return array<string, string>
     */
    public static function values(): array
    {
        return once(fn (): array => array_merge(self::DEFAULTS, Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => static::query()->pluck('value', 'key')->map(fn (?string $value): string => (string) $value)->all(),
        )));
    }

    public static function get(string $key): string
    {
        return self::values()[$key] ?? '';
    }

    public static function integer(string $key): int
    {
        return (int) self::get($key);
    }

    public static function enabled(string $key): bool
    {
        return self::get($key) === '1';
    }

    /**
     * Save several values at once.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }

        Cache::forget(self::CACHE_KEY);
        Once::flush();
    }
}
