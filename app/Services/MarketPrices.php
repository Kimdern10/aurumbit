<?php

namespace App\Services;

use App\Support\Btc;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Live Bitcoin and gold prices in US dollars from CoinGecko's free public API.
 * Gold is read from PAX Gold (PAXG), a token backed by one troy ounce of gold.
 *
 * Prices are for display only; no balance is ever worked out from them.
 * Responses are cached for five minutes, and the last good answer is kept so
 * the site still shows a price when the API is slow or down.
 */
class MarketPrices
{
    public const string URL = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,pax-gold&vs_currencies=usd&include_24hr_change=true';

    private const string CACHE_KEY = 'market-prices';

    private const string LAST_GOOD_KEY = 'market-prices:last-good';

    /**
     * Milligrams in one troy ounce, times 1000 so the maths stays in integers.
     */
    private const int MICROGRAMS_PER_TROY_OUNCE = 31_103_477;

    /**
     * Prices, fetching them when the cache is empty.
     *
     * @return array{btc_cents: int|null, btc_change: float|null, gold_cents: int|null, gold_change: float|null, updated_at: string|null}
     */
    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return self::normalize($cached);
        }

        $fresh = $this->fetch();

        if ($fresh !== null) {
            Cache::put(self::CACHE_KEY, $fresh, 300);
            Cache::forever(self::LAST_GOOD_KEY, $fresh);

            return $fresh;
        }

        // Try again in a minute; meanwhile show the last good prices.
        $fallback = $this->cached();
        Cache::put(self::CACHE_KEY, $fallback, 60);

        return $fallback;
    }

    /**
     * Prices already in the cache, without calling the API (for fast page loads).
     *
     * @return array{btc_cents: int|null, btc_change: float|null, gold_cents: int|null, gold_change: float|null, updated_at: string|null}
     */
    public function cached(): array
    {
        $cached = Cache::get(self::CACHE_KEY) ?? Cache::get(self::LAST_GOOD_KEY);

        return is_array($cached) ? self::normalize($cached) : self::empty();
    }

    /**
     * Read a cached entry back into a known shape.
     *
     * @param  array<mixed>  $prices
     * @return array{btc_cents: int|null, btc_change: float|null, gold_cents: int|null, gold_change: float|null, updated_at: string|null}
     */
    private static function normalize(array $prices): array
    {
        $integer = fn (string $key): ?int => is_int($prices[$key] ?? null) ? $prices[$key] : null;
        $float = fn (string $key): ?float => is_numeric($prices[$key] ?? null) ? (float) $prices[$key] : null;

        return [
            'btc_cents' => $integer('btc_cents'),
            'btc_change' => $float('btc_change'),
            'gold_cents' => $integer('gold_cents'),
            'gold_change' => $float('gold_change'),
            'updated_at' => is_string($prices['updated_at'] ?? null) ? $prices['updated_at'] : null,
        ];
    }

    /**
     * How many milligrams of gold an amount of BTC would buy at current prices.
     */
    public function goldMilligrams(int $sats): ?int
    {
        $prices = $this->cached();

        if ($prices['btc_cents'] === null || ! $prices['gold_cents']) {
            return null;
        }

        return intdiv(Btc::usdCents($sats, $prices['btc_cents']) * self::MICROGRAMS_PER_TROY_OUNCE, $prices['gold_cents'] * 1000);
    }

    /**
     * @return array{btc_cents: int|null, btc_change: float|null, gold_cents: int|null, gold_change: float|null, updated_at: string|null}|null
     */
    private function fetch(): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(4)->get(self::URL);
        } catch (ConnectionException) {
            return null;
        }

        $btc = $response->json('bitcoin.usd');
        $gold = $response->json('pax-gold.usd');

        if (! $response->successful() || ! is_numeric($btc)) {
            return null;
        }

        $btcChange = $response->json('bitcoin.usd_24h_change');
        $goldChange = $response->json('pax-gold.usd_24h_change');

        return [
            'btc_cents' => (int) round((float) $btc * 100),
            'btc_change' => is_numeric($btcChange) ? round((float) $btcChange, 2) : null,
            'gold_cents' => is_numeric($gold) ? (int) round((float) $gold * 100) : null,
            'gold_change' => is_numeric($goldChange) ? round((float) $goldChange, 2) : null,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{btc_cents: null, btc_change: null, gold_cents: null, gold_change: null, updated_at: null}
     */
    private static function empty(): array
    {
        return ['btc_cents' => null, 'btc_change' => null, 'gold_cents' => null, 'gold_change' => null, 'updated_at' => null];
    }
}
