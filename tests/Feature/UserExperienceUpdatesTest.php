<?php

use App\Enums\KycStatus;
use App\Enums\WithdrawalNetwork;
use App\Models\DepositWallet;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a manual credit reads as "Wallet funded" for the customer, never "Balance adjusted"', function () {
    Notification::fake();
    $user = User::factory()->create();

    app(WalletService::class)->adjust($user, 100_000_000, 'Welcome Bonus', User::factory()->admin()->create());

    Notification::assertSentTo($user, AccountActivity::class, fn (AccountActivity $notification): bool => $notification->title === 'Wallet funded'
        && $notification->body === '100.00 USDT has been added to your wallet (Welcome Bonus). Your balance is now 100.00 USDT.');

    $this->actingAs($user)->get(route('transactions', ['view' => 'balance']))
        ->assertSee('Wallet funded')
        ->assertDontSee('Balance adjust');
});

test('the calculator gets live coin prices so it can show the coin amount next to USDT', function () {
    Cache::put('market-prices', ['btc_cents' => 8_000_000, 'eth_cents' => 250_000, 'updated_at' => now()->toIso8601String()]);
    Plan::factory()->create(['category' => 'bitcoin']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('data-live-prices=\'{"btc":8000000,"eth":250000}\'', false)
        ->assertSee('"priceKey":"btc","ticker":"BTC"', false);
});

test('the market price command refreshes the cached prices', function () {
    Http::preventStrayRequests();
    Http::fake(['api.coingecko.com/*' => Http::response(['bitcoin' => ['usd' => 77359, 'usd_24h_change' => 1.5]])]);
    Cache::put('market-prices', ['btc_cents' => 1, 'updated_at' => now()->toIso8601String()]);

    $this->artisan('app:refresh-market-prices')->expectsOutputToContain('1 BTC = 77,359.00 USDT')->assertSuccessful();

    expect(Cache::get('market-prices')['btc_cents'])->toBe(7_735_900);
});

test('customer pages refresh themselves, and on deposit and withdraw only the history refreshes', function () {
    $user = User::factory()->create();
    DepositWallet::factory()->create();

    foreach (['dashboard', 'transactions', 'investments', 'notifications', 'referrals'] as $route) {
        $this->actingAs($user)->get(route($route))->assertSee('wire:poll.20s data-live-page', false);
    }

    foreach (['deposit', 'withdraw'] as $route) {
        $this->actingAs($user)->get(route($route))
            ->assertSee('data-live-history', false)
            ->assertDontSee('data-live-page', false);
    }
});

test('a deposit wallet QR code can be opened large', function () {
    $wallet = DepositWallet::factory()->create(['qr_path' => 'deposit-wallets/qr.png']);

    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->assertSee('Zoom in on the QR code')
        ->assertSee('data-qr-zoom', false)
        ->assertSee('Open full size')
        ->assertSee($wallet->qrUrl());
});

test('admins can cancel an approved verification with a reason, and the user must verify again', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $submission = KycSubmission::factory()->approved()->create();
    $user = $submission->user;

    $component = Livewire::actingAs($admin)->test('pages::admin.kyc')
        ->set('status', 'approved')
        ->assertSee('Cancel verification')
        ->call('review', $submission->id, 'revoke')
        ->call('confirm')
        ->assertHasErrors('note')
        ->set('note', 'Document looks edited')
        ->call('confirm')
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(KycStatus::Revoked)
        ->and($user->fresh()->isKycVerified())->toBeFalse();
    Notification::assertSentTo($user, AccountActivity::class, fn (AccountActivity $notification): bool => $notification->title === 'Identity verification cancelled');

    $component->set('status', 'all')->assertSee('Cancelled')->assertSee('Document looks edited');

    $this->actingAs($user)->get(route('kyc'))->assertSee('Your verification was cancelled by our team');
    expect(Livewire::actingAs($user)->test('pages::kyc')->get('canSubmit'))->toBeTrue();
});

test('only approved verifications can be cancelled', function () {
    $pending = KycSubmission::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.kyc')
        ->call('review', $pending->id, 'revoke')
        ->set('note', 'Not allowed')
        ->call('confirm');

    expect($pending->fresh()->status)->toBe(KycStatus::Pending);
});

test('the payout transaction ID is filled in from the Bybit withdrawal history', function () {
    config(['services.bybit.key' => 'test-key', 'services.bybit.secret' => 'test-secret']);
    Http::preventStrayRequests();
    $address = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    $txid = str_repeat('c9', 32);
    Http::fake(['https://api.bybit.com/v5/asset/withdraw/query-record*' => Http::response(['retCode' => 0, 'result' => ['rows' => [
        ['coin' => 'USDT', 'chain' => 'TRX', 'amount' => '200', 'txID' => $txid, 'toAddress' => $address, 'status' => 'success', 'createTime' => (string) now()->addMinute()->getTimestampMs()],
        ['coin' => 'USDT', 'chain' => 'TRX', 'amount' => '75', 'txID' => str_repeat('d1', 32), 'toAddress' => 'TSomeoneElseEntirelyXXXXXXXXXXXXXX', 'status' => 'success', 'createTime' => (string) now()->addMinute()->getTimestampMs()],
    ]]])]);
    $user = User::factory()->withBalance(500_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();
    Notification::fake();
    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 200_000_000, WithdrawalNetwork::Trc20, $address);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'approve')
        ->assertSet('payoutTxid', $txid)
        ->assertSee('Found in your Bybit withdrawals')
        ->call('confirm')
        ->assertHasNoErrors();

    expect($withdrawal->fresh()->payout_txid)->toBe($txid);
});

test('gold plans in the calculator are priced in ounces of gold', function () {
    Cache::put('market-prices', ['gold_cents' => 400_000, 'updated_at' => now()->toIso8601String()]);
    Plan::factory()->gold()->create();

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('"priceKey":"gold","ticker":"oz of gold"', false)
        ->assertSee('"gold":400000', false);
});
