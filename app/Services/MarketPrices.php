<?php

namespace App\Services;

use App\Support\Usdt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Live Bitcoin, gold, Ethereum and Solana prices in US dollars from CoinGecko's
 * free public API. Gold is read from PAX Gold (PAXG), a token backed by one
 * troy ounce of gold.
 *
 * Prices are for display only; no balance is ever worked out from them.
 * Responses are cached for a minute (refreshed every minute by the
 * app:refresh-market-prices schedule), and the last good answer is kept so
 * the site still shows a price when the API is slow or down.
 *
 * @phpstan-type Prices array{btc_cents: int|null, btc_change: float|null, gold_cents: int|null, gold_change: float|null, eth_cents: int|null, eth_change: float|null, sol_cents: int|null, sol_change: float|null, updated_at: string|null}
 */
class MarketPrices
{
    /**
     * Our short asset keys and their CoinGecko ids.
     */
    public const array ASSETS = ['btc' => 'bitcoin', 'gold' => 'pax-gold', 'eth' => 'ethereum', 'sol' => 'solana'];

    public const string URL = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,pax-gold,ethereum,solana&vs_currencies=usd&include_24hr_change=true';

    public const string CACHE_KEY = 'market-prices';

    private const string LAST_GOOD_KEY = 'market-prices:last-good';

    /**
     * Milligrams in one troy ounce, times 1000 so the maths stays in integers.
     */
    private const int MICROGRAMS_PER_TROY_OUNCE = 31_103_477;

    /**
     * Prices, fetching them when the cache is empty.
     *
     * @return Prices
     */
    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return self::normalize($cached);
        }

        $fresh = $this->fetch();

        if ($fresh !== null) {
            Cache::put(self::CACHE_KEY, $fresh, 60);
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
     * @return Prices
     */
    public function cached(): array
    {
        $cached = Cache::get(self::CACHE_KEY) ?? Cache::get(self::LAST_GOOD_KEY);

        return self::normalize(is_array($cached) ? $cached : []);
    }

    /**
     * The cached US dollar price of one asset in cents, e.g. priceCents('btc'),
     * or null when the asset is unknown or has no price yet.
     */
    public function priceCents(string $asset): ?int
    {
        return match ($asset) {
            'btc' => $this->cached()['btc_cents'],
            'eth' => $this->cached()['eth_cents'],
            'sol' => $this->cached()['sol_cents'],
            'gold' => $this->cached()['gold_cents'],
            default => null,
        };
    }

    /**
     * How many milligrams of gold an amount of USDT would buy at current prices.
     */
    public function goldMilligrams(int $micros): ?int
    {
        $prices = $this->cached();

        if (! $prices['gold_cents']) {
            return null;
        }

        return intdiv(Usdt::toUsdCents($micros) * self::MICROGRAMS_PER_TROY_OUNCE, $prices['gold_cents'] * 1000);
    }

    /**
     * Read a cached entry back into a known shape. Missing assets become null.
     *
     * @param  array<mixed>  $prices
     * @return Prices
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
            'eth_cents' => $integer('eth_cents'),
            'eth_change' => $float('eth_change'),
            'sol_cents' => $integer('sol_cents'),
            'sol_change' => $float('sol_change'),
            'updated_at' => is_string($prices['updated_at'] ?? null) ? $prices['updated_at'] : null,
        ];
    }

    /**
     * @return Prices|null
     */
    private function fetch(): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(4)->get(self::URL);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful() || ! is_numeric($response->json('bitcoin.usd'))) {
            return null;
        }

        $prices = ['updated_at' => now()->toIso8601String()];

        foreach (self::ASSETS as $key => $id) {
            $price = $response->json($id.'.usd');
            $change = $response->json($id.'.usd_24h_change');

            $prices[$key.'_cents'] = is_numeric($price) ? (int) round((float) $price * 100) : null;
            $prices[$key.'_change'] = is_numeric($change) ? round((float) $change, 2) : null;
        }

        return self::normalize($prices);
    }
}
