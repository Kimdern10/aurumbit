<?php

namespace App\Http\Middleware;

use App\Services\WalletService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps earnings and payouts up to date even when the scheduler is not
 * running: a customer's own plans are brought up to date at most once a
 * minute while they use the site, and everyone's when an admin does (at most
 * every five minutes). The hourly app:process-investments job does the same.
 */
class CatchUpInvestments
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $everyone = $user->is_admin;
            $key = $everyone ? 'investments-catch-up:all' : 'investments-catch-up:user:'.$user->id;

            if (Cache::add($key, true, $everyone ? 300 : 60)) {
                try {
                    app(WalletService::class)->processDueInvestments($everyone ? null : $user);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $next($request);
    }
}
