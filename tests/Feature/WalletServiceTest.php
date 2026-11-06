<?php

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Enums\RequestStatus;
use App\Enums\WithdrawalNetwork;
use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\Investment;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\MarketPrices;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;

const TRON_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

const ETH_ADDRESS = '0x742d35Cc6634C0532925a3b844Bc454e4438f44e';

beforeEach(function () {
    $this->wallet = app(WalletService::class);
    $this->admin = User::factory()->admin()->create();
    $this->usdt = DepositWallet::factory()->create();
});

function verifiedUser(int $balance = 0, array $attributes = []): User
{
    $user = User::factory()->withBalance($balance)->create($attributes);
    KycSubmission::factory()->approved()->for($user)->create();

    return $user;
}

test('approving a deposit credits the USDT amount and writes the audit log', function () {
    $user = User::factory()->create();
    $deposit = $this->wallet->requestDeposit($user, $this->usdt, '2500', str_repeat('ab', 32));

    expect($user->fresh()->balance_micros)->toBe(0)
        ->and($deposit->coin)->toBe('USDT')
        ->and($deposit->network)->toBe('TRC20')
        ->and($deposit->coin_amount)->toBe('2500');

    $this->wallet->approveDeposit($deposit, $this->admin, 2_500_000_000);

    expect($user->fresh()->balance_micros)->toBe(2_500_000_000)
        ->and($deposit->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($deposit->fresh()->credited_micros)->toBe(2_500_000_000)
        ->and($deposit->fresh()->reviewed_by)->toBe($this->admin->id);

    $entry = $user->ledgerEntries()->sole();
    expect($entry->type)->toBe(LedgerType::Deposit)
        ->and($entry->amount_micros)->toBe(2_500_000_000)
        ->and($entry->balance_after_micros)->toBe(2_500_000_000)
        ->and($entry->description)->toContain('2500 USDT')
        ->and($entry->actor_id)->toBe($this->admin->id);
});

test('a deposit in another coin is credited at the USDT value the admin confirms', function () {
    $eth = DepositWallet::factory()->create(['coin' => 'ETH', 'network' => 'Ethereum', 'address' => ETH_ADDRESS]);
    Cache::put('market-prices', ['eth_cents' => 250_000, 'updated_at' => now()->toIso8601String()]);

    $deposit = $this->wallet->requestDeposit($user = User::factory()->create(), $eth, '0.5');

    expect($deposit->coinLabel())->toBe('0.5 ETH')
        ->and($deposit->estimatedMicros(app(MarketPrices::class)))->toBe(1_250_000_000);

    $this->wallet->approveDeposit($deposit, $this->admin, 1_240_000_000);

    expect($user->fresh()->balance_micros)->toBe(1_240_000_000);
});

test('a deposit needs a positive USDT amount to credit', function () {
    $deposit = $this->wallet->requestDeposit(User::factory()->create(), $this->usdt, '100');

    expect(fn () => $this->wallet->approveDeposit($deposit, $this->admin, 0))->toThrow(WalletException::class, 'Enter the USDT amount to credit.')
        ->and($deposit->fresh()->status)->toBe(RequestStatus::Pending);
});

test('deposits to a wallet that is turned off are refused', function () {
    $closed = DepositWallet::factory()->inactive()->create();

    expect(fn () => $this->wallet->requestDeposit(User::factory()->create(), $closed, '100'))
        ->toThrow(WalletException::class, 'This wallet is not accepting deposits.');
});

test('a deposit cannot be reviewed twice', function () {
    $deposit = $this->wallet->requestDeposit(User::factory()->create(), $this->usdt, '1000');
    $this->wallet->approveDeposit($deposit, $this->admin, 1_000_000_000);

    expect(fn () => $this->wallet->approveDeposit($deposit, $this->admin, 1_000_000_000))->toThrow(WalletException::class)
        ->and(fn () => $this->wallet->rejectDeposit($deposit, $this->admin))->toThrow(WalletException::class)
        ->and($deposit->user->fresh()->balance_micros)->toBe(1_000_000_000);
});

test('rejecting a deposit leaves the balance unchanged', function () {
    $deposit = $this->wallet->requestDeposit($user = User::factory()->create(), $this->usdt, '1000');

    $this->wallet->rejectDeposit($deposit, $this->admin, 'Not received');

    expect($user->fresh()->balance_micros)->toBe(0)
        ->and($deposit->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($deposit->fresh()->admin_note)->toBe('Not received')
        ->and($user->ledgerEntries()->count())->toBe(0);
});

test('deposits worth less than the minimum are refused', function () {
    Setting::put(['min_deposit_micros' => 50_000_000]);

    expect(fn () => $this->wallet->requestDeposit(User::factory()->create(), $this->usdt, '49.99'))
        ->toThrow(WalletException::class, 'The smallest deposit is 50.00 USDT worth of USDT.');
});

test('the referrer gets a bonus on the first approved deposit only', function () {
    Setting::put(['referral_bonus_bps' => 100]);
    $referrer = User::factory()->create();
    $user = User::factory()->create(['referred_by_id' => $referrer->id]);

    $this->wallet->approveDeposit($this->wallet->requestDeposit($user, $this->usdt, '1000'), $this->admin, 1_000_000_000);
    $this->wallet->approveDeposit($this->wallet->requestDeposit($user, $this->usdt, '1000'), $this->admin, 1_000_000_000);

    expect($referrer->fresh()->balance_micros)->toBe(10_000_000)
        ->and($referrer->ledgerEntries()->where('type', LedgerType::ReferralBonus)->count())->toBe(1);
});

test('a withdrawal holds the amount straight away', function () {
    $user = verifiedUser(1_000_000_000);

    $withdrawal = $this->wallet->requestWithdrawal($user, 400_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS);

    expect($user->fresh()->balance_micros)->toBe(600_000_000)
        ->and($withdrawal->status)->toBe(RequestStatus::Pending)
        ->and($withdrawal->network)->toBe(WithdrawalNetwork::Trc20)
        ->and($user->ledgerEntries()->sole()->amount_micros)->toBe(-400_000_000);
});

test('withdrawals are paid in USDT on TRC20 or ERC20 to a valid address only', function (WithdrawalNetwork $network, string $address, bool $accepted) {
    $user = verifiedUser(1_000_000_000);

    if ($accepted) {
        expect($this->wallet->requestWithdrawal($user, 100_000_000, $network, $address)->network)->toBe($network);
    } else {
        expect(fn () => $this->wallet->requestWithdrawal($user, 100_000_000, $network, $address))->toThrow(WalletException::class);
        expect($user->fresh()->balance_micros)->toBe(1_000_000_000);
    }
})->with([
    'TRC20' => [WithdrawalNetwork::Trc20, TRON_ADDRESS, true],
    'ERC20' => [WithdrawalNetwork::Erc20, ETH_ADDRESS, true],
    'TRC20 with a mistyped character' => [WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6u', false],
    'ERC20 address on TRC20' => [WithdrawalNetwork::Trc20, ETH_ADDRESS, false],
    'TRC20 address on ERC20' => [WithdrawalNetwork::Erc20, TRON_ADDRESS, false],
    'Tron address for Bitcoin' => [WithdrawalNetwork::Bitcoin, TRON_ADDRESS, false],
]);

test('a withdrawal can never overdraw the balance', function () {
    $user = verifiedUser(500_000_000);

    expect(fn () => $this->wallet->requestWithdrawal($user, 500_000_001, WithdrawalNetwork::Trc20, TRON_ADDRESS))
        ->toThrow(WalletException::class, 'Your available balance is too low for this.');

    expect($user->fresh()->balance_micros)->toBe(500_000_000)
        ->and($user->withdrawals()->count())->toBe(0);
});

test('withdrawals below the minimum are refused', function () {
    Setting::put(['min_withdrawal_micros' => 10_000_000]);

    expect(fn () => $this->wallet->requestWithdrawal(verifiedUser(1_000_000_000), 9_990_000, WithdrawalNetwork::Trc20, TRON_ADDRESS))
        ->toThrow(WalletException::class, 'The smallest withdrawal is 10.00 USDT.');
});

test('two withdrawals cannot spend the same balance twice', function () {
    $user = verifiedUser(800_000_000);

    $this->wallet->requestWithdrawal($user, 500_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS);

    expect(fn () => $this->wallet->requestWithdrawal($user, 500_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS))->toThrow(WalletException::class);
    expect($user->fresh()->balance_micros)->toBe(300_000_000);
});

test('withdrawals need a verified identity when the setting is on', function () {
    $user = User::factory()->withBalance(1_000_000_000)->create();

    expect(fn () => $this->wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS))
        ->toThrow(WalletException::class, 'Please verify your identity before withdrawing.');

    Setting::put(['withdrawal_requires_kyc' => false]);

    $this->wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS);
    expect($user->fresh()->balance_micros)->toBe(900_000_000);
});

test('rejecting a withdrawal returns the held amount', function () {
    $user = verifiedUser(1_000_000_000);
    $withdrawal = $this->wallet->requestWithdrawal($user, 400_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS);

    $this->wallet->rejectWithdrawal($withdrawal, $this->admin, 'Wrong address');

    expect($user->fresh()->balance_micros)->toBe(1_000_000_000)
        ->and($withdrawal->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($user->ledgerEntries()->latest('id')->first()->type)->toBe(LedgerType::WithdrawalRefund);
});

test('approving a withdrawal keeps the amount deducted', function () {
    $user = verifiedUser(1_000_000_000);
    $withdrawal = $this->wallet->requestWithdrawal($user, 400_000_000, WithdrawalNetwork::Trc20, TRON_ADDRESS);

    $this->wallet->approveWithdrawal($withdrawal, $this->admin, str_repeat('ab', 32));

    expect($user->fresh()->balance_micros)->toBe(600_000_000)
        ->and($withdrawal->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($withdrawal->fresh()->payoutUrl())->toBe('https://tronscan.org/#/transaction/'.str_repeat('ab', 32))
        ->and(fn () => $this->wallet->rejectWithdrawal($withdrawal, $this->admin))->toThrow(WalletException::class);
});

test('the payment transaction ID must match the withdrawal network', function () {
    $user = verifiedUser(1_000_000_000);
    $erc20 = $this->wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Erc20, ETH_ADDRESS);

    expect(fn () => $this->wallet->approveWithdrawal($erc20, $this->admin, str_repeat('ab', 32)))->toThrow(WalletException::class, '0x');

    $this->wallet->approveWithdrawal($erc20, $this->admin, '0x'.str_repeat('AB', 32));

    expect($erc20->fresh()->payout_txid)->toBe('0x'.str_repeat('ab', 32))
        ->and($erc20->fresh()->payoutUrl())->toStartWith('https://etherscan.io/tx/0x');
});

test('investing moves money into the plan and copies its terms', function () {
    $user = User::factory()->withBalance(5_000_000)->create();
    $plan = Plan::factory()->create(['min_micros' => 1_000_000, 'max_micros' => 4_000_000, 'duration_days' => 30, 'target_return_bps' => 300]);

    $investment = $this->wallet->invest($user, $plan, 3_000_000);
    $plan->update(['target_return_bps' => 9_999]);

    expect($user->fresh()->balance_micros)->toBe(2_000_000)
        ->and($investment->fresh()->target_return_bps)->toBe(300)
        ->and($investment->ends_at->diffInDays($investment->starts_at, true))->toEqual(30)
        ->and($user->ledgerEntries()->sole()->type)->toBe(LedgerType::Investment);
});

test('investments outside the plan limits, above the balance or in closed plans are refused', function (int $amount, int $balance, bool $active) {
    $user = User::factory()->withBalance($balance)->create();
    $plan = Plan::factory()->create(['min_micros' => 1_000_000, 'max_micros' => 4_000_000, 'is_active' => $active]);

    expect(fn () => $this->wallet->invest($user, $plan, $amount))->toThrow(WalletException::class);
    expect($user->fresh()->balance_micros)->toBe($balance)
        ->and(Investment::count())->toBe(0);
})->with([
    'below minimum' => [999_999, 10_000_000, true],
    'above maximum' => [4_000_001, 10_000_000, true],
    'more than the balance' => [2_000_000, 1_999_999, true],
    'closed plan' => [2_000_000, 10_000_000, false],
]);

test('earnings accrue by whole days and never pass the plan rate', function () {
    $investment = Investment::factory()->create([
        'amount_micros' => 1_000_000,
        'target_return_bps' => 300,
        'duration_days' => 30,
        'starts_at' => now()->subDays(10)->subHour(),
        'ends_at' => now()->addDays(20)->subHour(),
    ]);

    $this->wallet->accrue($investment);
    $this->wallet->accrue($investment);

    expect($investment->fresh()->earned_micros)->toBe(10_000)
        ->and($investment->earningsAt(now()->addYear()))->toBe(30_000);
});

test('settling a finished investment returns principal plus earnings', function () {
    $user = User::factory()->withBalance(0)->create();
    $investment = Investment::factory()->for($user)->create([
        'amount_micros' => 1_000_000,
        'target_return_bps' => 300,
        'duration_days' => 30,
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subDay(),
    ]);

    $this->wallet->settle($investment);

    expect($user->fresh()->balance_micros)->toBe(1_030_000)
        ->and($investment->fresh()->status)->toBe(InvestmentStatus::Completed)
        ->and($investment->fresh()->earned_micros)->toBe(30_000)
        ->and($user->ledgerEntries()->pluck('type')->all())->toBe([LedgerType::InvestmentReturn, LedgerType::Earning])
        ->and(fn () => $this->wallet->settle($investment))->toThrow(WalletException::class);
});

test('an unfinished investment only settles early when an admin does it', function () {
    $investment = Investment::factory()->create(['starts_at' => now(), 'ends_at' => now()->addDays(30)]);

    expect(fn () => $this->wallet->settle($investment))->toThrow(WalletException::class, 'not finished');

    $this->wallet->settle($investment, $this->admin);

    expect($investment->fresh()->status)->toBe(InvestmentStatus::Completed)
        ->and($investment->user->fresh()->balance_micros)->toBe($investment->amount_micros);
});

test('cancelling returns only the principal', function () {
    $investment = Investment::factory()->create(['amount_micros' => 2_000_000, 'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20), 'earned_micros' => 5_000]);

    $this->wallet->cancel($investment, $this->admin);

    expect($investment->user->fresh()->balance_micros)->toBe(2_000_000)
        ->and($investment->fresh()->status)->toBe(InvestmentStatus::Cancelled);
});

test('admin adjustments are logged and cannot make a balance negative', function () {
    $user = User::factory()->withBalance(100_000)->create();

    $this->wallet->adjust($user, -40_000, 'Correction', $this->admin);

    expect($user->fresh()->balance_micros)->toBe(60_000)
        ->and($user->ledgerEntries()->sole()->actor_id)->toBe($this->admin->id)
        ->and(fn () => $this->wallet->adjust($user, -60_001, 'Too much', $this->admin))->toThrow(WalletException::class)
        ->and($user->fresh()->balance_micros)->toBe(60_000);
});

test('the process command accrues active and settles finished investments', function () {
    $finished = Investment::factory()->create(['amount_micros' => 1_000_000, 'starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);
    $running = Investment::factory()->create(['amount_micros' => 1_000_000, 'starts_at' => now()->subDays(15)->subHour(), 'ends_at' => now()->addDays(15)]);

    $this->artisan('app:process-investments')->assertSuccessful();

    expect($finished->fresh()->status)->toBe(InvestmentStatus::Completed)
        ->and($running->fresh()->status)->toBe(InvestmentStatus::Active)
        ->and($running->fresh()->earned_micros)->toBe(15_000);
});

test('the deposit model starts as pending', function () {
    expect(Deposit::factory()->create()->fresh()->status)->toBe(RequestStatus::Pending);
});

test('plans show their daily rate and exact profit', function () {
    $plan = Plan::factory()->make(['target_return_bps' => 350, 'duration_days' => 30]);

    expect($plan->targetPercent())->toBe('3.5')
        ->and($plan->dailyPercent())->toBe('0.117')
        ->and($plan->profitFor(10_000_000))->toBe(350_000)
        ->and($plan->profitFor(1))->toBe(0);
});
