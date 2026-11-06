<?php

namespace App\Console\Commands;

use App\Services\MarketPrices;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('app:refresh-market-prices')]
#[Description('Fetch the live BTC, ETH, SOL and gold prices so every page and calculation uses a fresh price')]
class RefreshMarketPrices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MarketPrices $prices): int
    {
        Cache::forget(MarketPrices::CACHE_KEY);
        $current = $prices->current();

        if ($current['btc_cents'] === null) {
            $this->components->warn('The price API did not answer; the last good prices are kept.');

            return self::FAILURE;
        }

        $this->components->info('Prices updated: 1 BTC = '.number_format($current['btc_cents'] / 100, 2).' USDT.');

        return self::SUCCESS;
    }
}
