<?php

namespace App\Services;

use App\Enums\WithdrawalNetwork;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Support\Btc;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reads the company Bybit account through Bybit's v5 API with a read-only key:
 * the live Funding account balance and the list of incoming crypto deposits.
 *
 * Nothing here can trade, transfer or withdraw. Answers are cached for a
 * minute so pages stay fast and Bybit's rate limits are respected. When the
 * key is missing or Bybit cannot be reached, the methods return null.
 *
 * @phpstan-type BybitDeposit array{coin: string, chain: string, amount: string, txid: string, to_address: string, status: string, credited: bool, at: string|null}
 */
class BybitAccount
{
    private const int RECV_WINDOW = 10_000;

    /**
     * Bybit deposit statuses that mean the money arrived in the account.
     */
    private const array CREDITED_STATUSES = ['3', '10012'];

    public function isConfigured(): bool
    {
        return filled(config('services.bybit.key')) && filled(config('services.bybit.secret'));
    }

    /**
     * Coins held in the Funding account (where crypto deposits land), e.g.
     * ['USDT' => '1250.5', 'BTC' => '0.01']. Coins with a zero balance are left out.
     *
     * @return array<string, string>|null
     */
    public function fundingBalances(): ?array
    {
        $result = $this->cached('bybit:funding-balances', '/v5/asset/transfer/query-account-coins-balance', ['accountType' => 'FUND']);

        if ($result === null) {
            return null;
        }

        $balances = [];

        foreach ($result['balance'] ?? [] as $row) {
            if (is_array($row) && is_string($row['coin'] ?? null) && is_numeric($row['walletBalance'] ?? null) && (float) $row['walletBalance'] > 0) {
                $balances[$row['coin']] = (string) $row['walletBalance'];
            }
        }

        ksort($balances);

        return $balances;
    }

    /**
     * Crypto deposits into the Bybit account over the last 30 days, newest first.
     *
     * @return list<BybitDeposit>|null
     */
    public function deposits(): ?array
    {
        $result = $this->cached('bybit:deposits', '/v5/asset/deposit/query-record', ['limit' => 50]);

        if ($result === null) {
            return null;
        }

        $deposits = [];

        foreach ($result['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $deposits[] = [
                'coin' => (string) ($row['coin'] ?? ''),
                'chain' => (string) ($row['chain'] ?? ''),
                'amount' => (string) ($row['amount'] ?? '0'),
                'txid' => (string) ($row['txID'] ?? ''),
                'to_address' => (string) ($row['toAddress'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'credited' => in_array((string) ($row['status'] ?? ''), self::CREDITED_STATUSES, true),
                'at' => is_numeric($row['successAt'] ?? null) && (int) $row['successAt'] > 0
                    ? now()->createFromTimestampMs((int) $row['successAt'])->toIso8601String()
                    : null,
            ];
        }

        return $deposits;
    }

    /**
     * Payouts sent from the Bybit account over the last 30 days, newest first.
     *
     * @return list<array{coin: string, chain: string, amount: string, txid: string, to_address: string, status: string, at: string|null}>|null
     */
    public function withdrawals(): ?array
    {
        $result = $this->cached('bybit:withdrawals', '/v5/asset/withdraw/query-record', ['limit' => 50]);

        if ($result === null) {
            return null;
        }

        $withdrawals = [];

        foreach ($result['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $withdrawals[] = [
                'coin' => (string) ($row['coin'] ?? ''),
                'chain' => (string) ($row['chain'] ?? ''),
                'amount' => (string) ($row['amount'] ?? '0'),
                'txid' => (string) ($row['txID'] ?? ''),
                'to_address' => (string) ($row['toAddress'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'at' => is_numeric($row['createTime'] ?? null) ? now()->createFromTimestampMs((int) $row['createTime'])->toIso8601String() : null,
            ];
        }

        return $withdrawals;
    }

    /**
     * The blockchain transaction ID of the Bybit payout that paid this customer
     * withdrawal: same coin, sent to the same address after the request was made,
     * and already given a transaction ID by Bybit. Null when there is none yet.
     *
     * @return array{txid: string, amount: string, coin: string, chain: string}|null
     */
    public function findPayoutFor(Withdrawal $withdrawal): ?array
    {
        foreach ($this->withdrawals() ?? [] as $payout) {
            $sameAddress = $withdrawal->network === WithdrawalNetwork::Trc20 || $withdrawal->network === WithdrawalNetwork::Solana || $withdrawal->network === WithdrawalNetwork::Bitcoin
                ? $payout['to_address'] === $withdrawal->address
                : strcasecmp($payout['to_address'], $withdrawal->address) === 0;
            $afterRequest = $payout['at'] === null || $withdrawal->created_at === null || Carbon::parse($payout['at'])->gte($withdrawal->created_at->copy()->subMinutes(5));

            if ($payout['txid'] !== '' && $sameAddress && strcasecmp($payout['coin'], $withdrawal->coin) === 0 && $afterRequest) {
                return ['txid' => $payout['txid'], 'amount' => $payout['amount'], 'coin' => $payout['coin'], 'chain' => $payout['chain']];
            }
        }

        return null;
    }

    /**
     * The Bybit deposit with this blockchain transaction ID, if Bybit has seen it.
     *
     * @return BybitDeposit|null
     */
    public function findDeposit(?string $txid): ?array
    {
        if (blank($txid)) {
            return null;
        }

        foreach ($this->deposits() ?? [] as $deposit) {
            if ($deposit['txid'] !== '' && strcasecmp($deposit['txid'], trim($txid)) === 0) {
                return $deposit;
            }
        }

        return null;
    }

    /**
     * Compare a customer's deposit with what Bybit actually received under the
     * same transaction ID: "match" (same coin and amount, credited), "different"
     * (found, but the coin, amount or status differs), "missing" (Bybit has no
     * such deposit in the last 30 days), or null when the key is not set up.
     *
     * @return array{state: 'match'|'different'|'missing', bybit: BybitDeposit|null}|null
     */
    public function checkDeposit(Deposit $deposit): ?array
    {
        if (! $this->isConfigured() || $this->deposits() === null) {
            return null;
        }

        $found = $this->findDeposit($deposit->reference);

        if ($found === null) {
            return ['state' => 'missing', 'bybit' => null];
        }

        $sameAmount = Btc::toSats(self::plainAmount($found['amount'])) === Btc::toSats(self::plainAmount($deposit->coin_amount));
        $isMatch = $found['credited'] && strcasecmp($found['coin'], $deposit->coin) === 0 && $sameAmount;

        return ['state' => $isMatch ? 'match' : 'different', 'bybit' => $found];
    }

    /**
     * An amount as plain decimal text with at most 8 decimals, e.g. "500" or "0.01".
     */
    public static function plainAmount(string $amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 8, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * When the cached answers were fetched, for "updated … ago" labels.
     */
    public function updatedAt(): ?string
    {
        return Cache::get('bybit:updated-at');
    }

    /**
     * Drop the cached answers so the next read asks Bybit again.
     */
    public function refresh(): void
    {
        Cache::forget('bybit:funding-balances');
        Cache::forget('bybit:deposits');
        Cache::forget('bybit:withdrawals');
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>|null
     */
    private function cached(string $cacheKey, string $path, array $query): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->get($path, $query);

        if ($result !== null) {
            Cache::put($cacheKey, $result, 60);
            Cache::put('bybit:updated-at', now()->toIso8601String(), 3600);
        }

        return $result;
    }

    /**
     * A signed GET request: HMAC-SHA256 of timestamp + key + recv window + query string.
     *
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        $key = (string) config('services.bybit.key');
        $timestamp = (string) now()->getTimestampMs();
        $queryString = http_build_query($query);

        try {
            $response = Http::acceptJson()
                ->connectTimeout(10)
                ->timeout(15)
                ->retry(2, 500, fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->withHeaders([
                    'X-BAPI-API-KEY' => $key,
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => (string) self::RECV_WINDOW,
                    'X-BAPI-SIGN' => hash_hmac('sha256', $timestamp.$key.self::RECV_WINDOW.$queryString, (string) config('services.bybit.secret')),
                ])
                ->get(rtrim((string) config('services.bybit.base_url'), '/').$path.'?'.$queryString);
        } catch (ConnectionException $exception) {
            report($exception);

            return null;
        }

        if (! $response->successful() || $response->json('retCode') !== 0 || ! is_array($response->json('result'))) {
            report(new \RuntimeException('Bybit API '.$path.' failed: '.$response->status().' '.($response->json('retMsg') ?? 'no message')));

            return null;
        }

        return $response->json('result');
    }
}
