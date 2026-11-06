<?php

use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\User;
use App\Services\BybitAccount;
use App\Services\WalletService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const BALANCE_URL = 'https://api.bybit.com/v5/asset/transfer/query-account-coins-balance*';

const DEPOSITS_URL = 'https://api.bybit.com/v5/asset/deposit/query-record*';

const BYBIT_TXID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

/**
 * Fake a Bybit account holding 1,250.5 USDT that received one 500 USDT deposit.
 */
function fakeBybit(string $depositAmount = '500'): void
{
    config(['services.bybit.key' => 'test-key', 'services.bybit.secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake([
        BALANCE_URL => Http::response(['retCode' => 0, 'retMsg' => 'OK', 'result' => ['balance' => [
            ['coin' => 'USDT', 'walletBalance' => '1250.5'],
            ['coin' => 'BTC', 'walletBalance' => '0'],
        ]]]),
        DEPOSITS_URL => Http::response(['retCode' => 0, 'retMsg' => 'OK', 'result' => ['rows' => [
            ['coin' => 'USDT', 'chain' => 'TRX', 'amount' => $depositAmount, 'txID' => BYBIT_TXID, 'toAddress' => 'TGzZd4tCgpEcwYSbTQbViAfommUJpwHgsi', 'status' => 3, 'successAt' => '1793400000000'],
        ]]]),
    ]);
}

test('the balance is read with a signed read-only request and empty coins are left out', function () {
    fakeBybit();

    expect(app(BybitAccount::class)->fundingBalances())->toBe(['USDT' => '1250.5']);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->header('X-BAPI-TIMESTAMP')[0].'test-key'.$request->header('X-BAPI-RECV-WINDOW')[0].'accountType=FUND';

        return $request->method() === 'GET'
            && $request->header('X-BAPI-API-KEY')[0] === 'test-key'
            && $request->header('X-BAPI-SIGN')[0] === hash_hmac('sha256', $payload, 'test-secret');
    });
});

test('answers are cached for a minute so pages do not hit Bybit on every load', function () {
    fakeBybit();
    $bybit = app(BybitAccount::class);

    $bybit->fundingBalances();
    $bybit->fundingBalances();
    Http::assertSentCount(1);

    $bybit->refresh();
    $bybit->fundingBalances();
    Http::assertSentCount(2);
});

test('nothing is called and nothing is shown without a key', function () {
    Http::preventStrayRequests();

    expect(app(BybitAccount::class)->fundingBalances())->toBeNull()
        ->and(app(BybitAccount::class)->checkDeposit(Deposit::factory()->create()))->toBeNull();
});

test('a Bybit error is reported and treated as no answer', function () {
    config(['services.bybit.key' => 'test-key', 'services.bybit.secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake([BALANCE_URL => Http::response(['retCode' => 10003, 'retMsg' => 'API key is invalid.', 'result' => []])]);

    expect(app(BybitAccount::class)->fundingBalances())->toBeNull();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.bybit-account')
        ->assertSee('Bybit did not answer');
});

test('customer deposits are checked against what Bybit received', function (?string $txid, string $coinAmount, string $state) {
    fakeBybit();
    $deposit = Deposit::factory()->create(['reference' => $txid, 'coin' => 'USDT', 'coin_amount' => $coinAmount]);

    expect(app(BybitAccount::class)->checkDeposit($deposit)['state'])->toBe($state);
})->with([
    'same transaction and amount' => [BYBIT_TXID, '500', 'match'],
    'transaction ID typed in capitals' => [strtoupper(BYBIT_TXID), '500.00', 'match'],
    'customer claims more than arrived' => [BYBIT_TXID, '5000', 'different'],
    'unknown transaction ID' => [str_repeat('f', 64), '500', 'missing'],
    'no transaction ID' => [null, '500', 'missing'],
]);

test('admins see the Bybit check on deposits and the amount Bybit really received is suggested', function () {
    fakeBybit('450');
    $deposit = Deposit::factory()->create(['reference' => BYBIT_TXID, 'coin' => 'USDT', 'coin_amount' => '500']);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.deposits')
        ->assertSee('Bybit received 450 USDT')
        ->call('startApprove', $deposit->id)
        ->assertSet('creditUsdt', '450.00')
        ->assertSee('This is not what the customer entered');
});

test('the admin sees the live Bybit balance and which customer claimed each Bybit deposit', function () {
    fakeBybit();
    $deposit = Deposit::factory()->create(['reference' => BYBIT_TXID]);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.bybit-account')
        ->assertSee('1,250.50')
        ->assertSee('500 USDT')
        ->assertSee($deposit->code());
});

test('real money is kept apart from admin adjustments, earnings and bonuses', function () {
    Notification::fake();
    $wallet = app(WalletService::class);
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $depositWallet = DepositWallet::factory()->create();

    $wallet->approveDeposit($wallet->requestDeposit($customer, $depositWallet, '1000'), $admin, 1_000_000_000);
    $wallet->adjust($customer, 5_000_000_000, 'Manual credit', $admin);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Real money deposited', '1,000.00', 'Still in your wallets', '1,000.00'])
        ->assertSee('Includes +5,000.00 USDT from admin adjustments')
        ->assertSeeInOrder(['Admin adjustments', '+5,000.00'])
        ->assertSeeInOrder(['Shortfall if everyone withdrew today', '5,000.00']);

    $this->actingAs($admin)->get(route('admin.wallets'))
        ->assertSeeInOrder(['Real money credited:', '1,000.00']);
});

test('without a key, old cached Bybit answers are not shown', function () {
    Cache::put('bybit:funding-balances', ['balance' => [['coin' => 'USDT', 'walletBalance' => '1']]]);

    expect(app(BybitAccount::class)->fundingBalances())->toBeNull();
});
