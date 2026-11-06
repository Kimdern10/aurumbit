<?php

use App\Enums\WithdrawalNetwork;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\KycSubmission;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AccountActivity;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->wallet = app(WalletService::class);
    $this->admin = User::factory()->admin()->create();
});

test('a user can open a printable receipt for their deposit', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $deposit = $this->wallet->requestDeposit($user, DepositWallet::factory()->create(), '250', str_repeat('ab', 32));
    $this->wallet->approveDeposit($deposit, $this->admin, 250_000_000);

    $this->actingAs($user)->get($deposit->receiptUrl())
        ->assertOk()
        ->assertSee('Deposit receipt')
        ->assertSee($deposit->code())
        ->assertSee('Ada Lovelace')
        ->assertSee('250 USDT')
        ->assertSee('250.00 USDT')
        ->assertSee(str_repeat('ab', 32))
        ->assertSee('Approved')
        ->assertSee('Balance after')
        ->assertSee('Print / Save as PDF')
        ->assertSee('window.print()', false);
});

test('a withdrawal receipt shows the coin paid, the price used and the address', function () {
    Cache::put('market-prices', ['btc_cents' => 8_000_000, 'updated_at' => now()->toIso8601String()]);
    $user = User::factory()->withBalance(1_000_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();
    $withdrawal = $this->wallet->requestWithdrawal($user, 800_000_000, WithdrawalNetwork::Bitcoin, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');

    $this->actingAs($user)->get($withdrawal->receiptUrl())
        ->assertOk()
        ->assertSee('Withdrawal receipt')
        ->assertSee($withdrawal->code())
        ->assertSee('0.01000000 BTC')
        ->assertSee('800.00 USDT')
        ->assertSee('1 BTC = 80,000.00 USDT')
        ->assertSee('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')
        ->assertSee('Waiting for review');
});

test('users cannot open other people\'s receipts, but admins can', function () {
    $deposit = Deposit::factory()->create();
    $withdrawal = Withdrawal::factory()->create();

    $this->actingAs(User::factory()->create())->get($deposit->receiptUrl())->assertNotFound();
    $this->actingAs(User::factory()->create())->get($withdrawal->receiptUrl())->assertNotFound();

    $this->actingAs($this->admin)->get($deposit->receiptUrl(true))->assertOk()->assertSee($deposit->code());
    $this->actingAs($this->admin)->get($withdrawal->receiptUrl(true))->assertOk()->assertSee($withdrawal->code());
});

test('transactions has one place for deposits and withdrawals, each with a receipt, and the balance log', function () {
    $user = User::factory()->create();
    $deposit = $this->wallet->requestDeposit($user, DepositWallet::factory()->create(), '250');
    $this->wallet->approveDeposit($deposit, $this->admin, 250_000_000);

    Livewire::actingAs($user)->test('pages::transactions')
        ->assertSet('view', 'requests')
        ->assertSee($deposit->code())
        ->assertSee($deposit->receiptUrl())
        ->set('view', 'balance')
        ->assertSee('Deposit '.$deposit->code().' credited')
        ->assertSee('View receipt');

    $this->actingAs($user)->get(route('dashboard'))->assertDontSee(route('history'));
});

test('notifications link straight to the receipt', function () {
    $user = User::factory()->create();
    $deposit = $this->wallet->requestDeposit($user, DepositWallet::factory()->create(), '250');

    Notification::assertSentTo($user, AccountActivity::class, fn ($notification): bool => $notification->url === $deposit->receiptUrl());
});

test('the sign-up form has a referral code field filled from the invite link', function () {
    $this->get(route('register', ['ref' => 'abc123']))
        ->assertOk()
        ->assertSee('Referral code (optional)')
        ->assertSee('value="ABC123"', false);
});

test('a typed referral code links the new user to who invited them', function () {
    $referrer = User::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
        'ref' => ' '.strtolower($referrer->referral_code).' ',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'new@example.com')->sole()->referred_by_id)->toBe($referrer->id);
});

test('an unknown referral code is refused with a clear message, and leaving it empty is fine', function () {
    $this->post(route('register.store'), [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
        'ref' => 'NOPE999',
    ])->assertSessionHasErrors(['ref' => 'We could not find that referral code. Check it with the person who invited you, or leave it empty.']);

    $this->post(route('register.store'), [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
        'ref' => '',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'new@example.com')->sole()->referred_by_id)->toBeNull();
});
