<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\MarketPrices;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * The landing page. Prices come from the cache so the page never waits on
     * the price API; the browser refreshes them afterwards.
     */
    public function __invoke(MarketPrices $prices): View
    {
        return view('home', [
            'plans' => Plan::query()->available()->get(),
            'prices' => $prices->cached(),
        ]);
    }
}
