<?php

use App\Enums\PlanCategory;
use App\Enums\RequestStatus;
use App\Enums\WithdrawalNetwork;
use App\Exceptions\WalletException;
use App\Models\DepositWallet;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function payoutUser(int $balance = 1_000_000_000): User
{
    $user = User::factory()->withBalance($balance)->create();
    KycSubmission::factory()->approved()->for($user)->create();

    return $user;
}

function livePrices(): void
{
    Cache::put('market-prices', [
        'btc_cents' => 8_000_000,
        'btc_change' => 1.25,
        'eth_cents' => 250_000,
        'sol_cents' => 20_000,
        'gold_cents' => 250_000,
        'updated_at' => now()->toIso8601String(),
    ]);
}

test('a user can withdraw in BTC, ETH or SOL at the live price, fixed when they ask', function (WithdrawalNetwork $network, string $address, string $coinAmount) {
    livePrices();
    $user = payoutUser();

    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 1_000_000_000, $network, $address);

    expect($withdrawal->coin)->toBe($network->coin())
        ->and($withdrawal->coin_amount)->toBe($coinAmount)
        ->and($withdrawal->payoutLabel())->toBe($coinAmount.' '.$network->coin())
        ->and($user->fresh()->balance_micros)->toBe(0);

    Cache::put('market-prices', ['btc_cents' => 1, 'eth_cents' => 1, 'sol_cents' => 1]);

    expect($withdrawal->fresh()->coin_amount)->toBe($coinAmount);
})->with([
    'BTC' => [WithdrawalNetwork::Bitcoin, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', '0.01250000'],
    'ETH' => [WithdrawalNetwork::Ethereum, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', '0.40000000'],
    'SOL' => [WithdrawalNetwork::Solana, 'So11111111111111111111111111111111111111112', '5.00000000'],
]);

test('a coin withdrawal is refused when the live price is missing, and nothing is held', function () {
    Cache::forget('market-prices');
    $user = payoutUser();

    expect(fn () => app(WalletService::class)->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Bitcoin, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'))
        ->toThrow(WalletException::class, 'withdraw in USDT');

    expect($user->fresh()->balance_micros)->toBe(1_000_000_000);
});

test('the withdraw page shows how much of the chosen coin the user receives', function () {
    livePrices();

    Livewire::actingAs(payoutUser())->test('pages::withdraw')
        ->set('amount', '800')
        ->set('network', 'bitcoin')
        ->assertSee('You receive about 0.01000000 BTC')
        ->set('network', 'trc20')
        ->assertDontSee('You receive about');
});

test('Solana payouts need a Solana transaction signature', function () {
    livePrices();
    $withdrawal = app(WalletService::class)->requestWithdrawal(payoutUser(), 100_000_000, WithdrawalNetwork::Solana, 'So11111111111111111111111111111111111111112');
    $admin = User::factory()->admin()->create();
    $signature = str_repeat('5Kd3', 22);

    expect(fn () => app(WalletService::class)->approveWithdrawal($withdrawal, $admin, str_repeat('ab', 32)))->toThrow(WalletException::class);

    app(WalletService::class)->approveWithdrawal($withdrawal, $admin, $signature);

    expect($withdrawal->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($withdrawal->fresh()->payout_txid)->toBe($signature)
        ->and($withdrawal->fresh()->payoutUrl())->toBe('https://solscan.io/tx/'.$signature);
});

test('the admin sees exactly which coin and amount to send', function () {
    livePrices();
    app(WalletService::class)->requestWithdrawal(payoutUser(), 800_000_000, WithdrawalNetwork::Bitcoin, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.withdrawals')
        ->assertSee('Send 0.01000000 BTC')
        ->assertSee('80,000.00 USDT per BTC');
});

test('USDT plans can be offered and each plan shows its live coin price', function () {
    livePrices();
    Plan::factory()->create(['name' => 'Stable Saver', 'category' => PlanCategory::Usdt]);
    Plan::factory()->create(['name' => 'Bitcoin Plus', 'category' => PlanCategory::Bitcoin]);

    $this->actingAs(User::factory()->create())->get(route('invest'))
        ->assertOk()
        ->assertSee('USDT plans')
        ->assertSee('Stable Saver')
        ->assertSee('data-price="btc"', false)
        ->assertSee('BTC/USDT')
        ->assertSee('80,000.00')
        ->assertDontSee('$80,000.00')
        ->assertSee('+1.25%')
        ->assertSee('≈ 0.01250000 BTC');

    $this->get(route('home'))->assertOk()->assertSee('USDT plans')->assertSee('Stable Saver');
});

test('the converter is shown with live prices on the money pages', function (string $route) {
    livePrices();

    $this->actingAs(User::factory()->create())->get(route($route))
        ->assertOk()
        ->assertSee('data-converter', false)
        ->assertSee('"BTC":80000', false);
})->with(['dashboard', 'deposit', 'withdraw', 'invest']);

test('an admin uploads a QR code for a wallet and customers see it', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $wallet = DepositWallet::factory()->create();

    Livewire::actingAs($admin)->test('pages::admin.wallets')
        ->call('edit', $wallet->id)
        ->set('qrImage', UploadedFile::fake()->image('qr.png', 300, 300))
        ->call('save')
        ->assertHasNoErrors();

    $path = $wallet->fresh()->qr_path;
    Storage::disk('public')->assertExists($path);

    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->assertSee($wallet->fresh()->qrUrl());

    Livewire::actingAs($admin)->test('pages::admin.wallets')
        ->call('edit', $wallet->id)
        ->set('removeQr', true)
        ->call('save');

    expect($wallet->fresh()->qr_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('wallet QR codes must be images', function () {
    Storage::fake('public');

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.wallets')
        ->call('edit', DepositWallet::factory()->create()->id)
        ->set('qrImage', UploadedFile::fake()->create('qr.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('qrImage');
});
