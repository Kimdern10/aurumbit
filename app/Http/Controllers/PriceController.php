<?php

namespace App\Http\Controllers;

use App\Services\MarketPrices;
use Illuminate\Http\JsonResponse;

class PriceController extends Controller
{
    /**
     * Current BTC and gold prices for the tickers, refreshed by the browser.
     */
    public function __invoke(MarketPrices $prices): JsonResponse
    {
        return response()->json($prices->current())->header('Cache-Control', 'public, max-age=60');
    }
}
