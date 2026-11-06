<?php

use App\Enums\RequestStatus;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Services\DepositAutoCredit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Fake Bybit receiving the given deposits, as [coin, amount, txid, toAddress, status].
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: string, 4?: int}>  $rows
 */
function bybitReceived(array $rows): void
{
    config(['services.bybit.key' => 'test-key', 'services.bybit.secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake(['https://api.bybit.com/v5/asset/deposit/query-record*' => Http::response(['retCode' => 0, 'retMsg' => 'OK', 'result' => ['rows' => array_map(fn (array $row): array => [
        'coin' => $row[0], 'chain' => 'TRX', 'amount' => $row[1], 'txID' => $row[2], 'toAddress' => $row[3], 'status' => $row[4] ?? 3, 'successAt' => '1793400000000',
    ], $rows)]])]);
}

function pendingDeposit(string $coin, string $amount, string $txid, ?DepositWallet $wallet = null): Deposit
{
    $wallet ??= DepositWallet::factory()->create();

    return Deposit::factory()->for(User::factory())->create([
        'deposit_wallet_id' => $wallet->id, 'coin' => $coin, 'network' => $wallet->network, 'coin_amount' => $amount, 'reference' => $txid,
    ]);
}

beforeEach(function () {
    Notification::fake();
    Setting::put(['auto_credit_deposits' => '1']);
});

test('a USDT deposit Bybit confirms is credited without an admin', function () {
    $deposit = pendingDeposit('USDT', '250', str_repeat('a', 64));
    bybitReceived([['USDT', '250', str_repeat('a', 64), $deposit->wallet->address]]);

    expect(app(DepositAutoCredit::class)->run())->toBe(['credited' => 1, 'left' => 0]);

    $deposit->refresh();
    expect($deposit->status)->toBe(RequestStatus::Approved)
        ->and($deposit->credited_micros)->toBe(250_000_000)
        ->and($deposit->reviewed_by)->toBeNull()
        ->and($deposit->admin_note)->toBe(DepositAutoCredit::NOTE)
        ->and($deposit->user->balance_micros)->toBe(250_000_000);

    Notification::assertSentTo($deposit->user, AccountActivity::class, fn (AccountActivity $n): bool => $n->title === 'Deposit credited');

    expect(app(DepositAutoCredit::class)->run())->toBe(['credited' => 0, 'left' => 0])
        ->and($deposit->user->fresh()->balance_micros)->toBe(250_000_000);
});

test('deposits that do not fully match are left for an admin', function (array $bybitRow, string $coin = 'USDT', string $amount = '250') {
    $deposit = pendingDeposit($coin, $amount, str_repeat('b', 64));
    bybitReceived([array_map(fn ($value) => $value === 'WALLET' ? $deposit->wallet->address : $value, $bybitRow)]);

    expect(app(DepositAutoCredit::class)->run())->toBe(['credited' => 0, 'left' => 1])
        ->and($deposit->fresh()->status)->toBe(RequestStatus::Pending)
        ->and($deposit->user->fresh()->balance_micros)->toBe(0);
})->with([
    'not on Bybit' => [['USDT', '250', 'other-txid-'.str_repeat('c', 50), 'WALLET']],
    'smaller amount on Bybit' => [['USDT', '200', str_repeat('b', 64), 'WALLET']],
    'different coin on Bybit' => [['USDC', '250', str_repeat('b', 64), 'WALLET']],
    'still confirming on Bybit' => [['USDT', '250', str_repeat('b', 64), 'WALLET', 0]],
    'sent to a different address' => [['USDT', '250', str_repeat('b', 64), 'TGzZd4tCgpEcwYSbTQbViAfommUJpwHgsi']],
    'above the auto-credit limit' => [['USDT', '1500', str_repeat('b', 64), 'WALLET'], 'USDT', '1500'],
]);

test('other coins wait for an admin unless the owner allows the live price', function () {
    Cache::put('market-prices', ['btc_cents' => 6_000_000, 'updated_at' => now()->toIso8601String()]);
    $wallet = DepositWallet::factory()->bitcoin()->create();
    $deposit = pendingDeposit('BTC', '0.01', str_repeat('d', 64), $wallet);
    bybitReceived([['BTC', '0.01', str_repeat('d', 64), $wallet->address]]);

    expect(app(DepositAutoCredit::class)->run()['credited'])->toBe(0);

    Setting::put(['auto_credit_crypto' => '1']);

    expect(app(DepositAutoCredit::class)->run()['credited'])->toBe(1)
        ->and($deposit->fresh()->credited_micros)->toBe(600_000_000);
});

test('nothing is credited while auto-credit is switched off', function () {
    Setting::put(['auto_credit_deposits' => '0']);
    $deposit = pendingDeposit('USDT', '250', str_repeat('e', 64));
    bybitReceived([['USDT', '250', str_repeat('e', 64), $deposit->wallet->address]]);

    $this->artisan('app:auto-credit-deposits')->expectsOutputToContain('Auto-credit is off')->assertSuccessful();

    expect($deposit->fresh()->status)->toBe(RequestStatus::Pending);
    Http::assertNothingSent();
});

test('owners turn auto-credit on in settings and can run it from the deposits page', function () {
    $owner = User::factory()->admin()->create();
    Setting::put(['auto_credit_deposits' => '0']);

    Livewire::actingAs($owner)->test('pages::admin.settings')
        ->set('autoCreditDeposits', true)
        ->set('autoCreditMax', '500')
        ->call('save')
        ->assertHasNoErrors();

    expect(DepositAutoCredit::isEnabled())->toBeTrue()
        ->and(Setting::integer('auto_credit_max_micros'))->toBe(500_000_000);

    $deposit = pendingDeposit('USDT', '100', str_repeat('f', 64));
    bybitReceived([['USDT', '100', str_repeat('f', 64), $deposit->wallet->address]]);

    Livewire::actingAs($owner)->test('pages::admin.deposits')
        ->assertSee('Credit confirmed now')
        ->call('autoCreditNow');

    expect($deposit->fresh()->status)->toBe(RequestStatus::Approved);

    Livewire::actingAs(User::factory()->support()->create())->test('pages::admin.deposits')
        ->assertDontSee('Credit confirmed now')
        ->call('autoCreditNow')
        ->assertForbidden();
});
