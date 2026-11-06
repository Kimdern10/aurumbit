<?php

use App\Enums\KycStatus;
use App\Enums\RequestStatus;
use App\Enums\WithdrawalNetwork;
use App\Models\Announcement;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\KycSubmission;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

$adminRoutes = ['admin.dashboard', 'admin.users', 'admin.plans', 'admin.deposits', 'admin.withdrawals', 'admin.investments', 'admin.kyc', 'admin.announcements', 'admin.wallets', 'admin.tickets', 'admin.ledger', 'admin.settings'];

test('admins can open every admin page', function (string $route) {
    $admin = User::factory()->admin()->create();
    LedgerEntry::factory()->create();

    $this->actingAs($admin)->get(route($route))->assertOk();
})->with($adminRoutes);

test('regular users cannot open admin pages', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
})->with($adminRoutes);

test('an admin can open a user and adjust their balance', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withBalance(100_000_000)->create();

    $this->actingAs($admin)->get(route('admin.users.show', $user))->assertOk()->assertSee($user->email);

    Livewire::actingAs($admin)->test('pages::admin.user', ['user' => $user])
        ->set('adjustAmount', '-40.50')
        ->set('adjustReason', 'Duplicate credit')
        ->call('adjust')
        ->assertHasNoErrors();

    expect($user->fresh()->balance_micros)->toBe(59_500_000)
        ->and($user->ledgerEntries()->sole()->description)->toBe('Duplicate credit');

    $this->actingAs($user)->get(route('transactions', ['view' => 'balance']))
        ->assertSee('Wallet debited')
        ->assertDontSee('Balance adjustment')
        ->assertSee('Duplicate credit')
        ->assertDontSee('Admin')
        ->assertDontSee($admin->name);
});

test('an admin suspends a user for a set time with a reason, and the suspension lifts itself', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Livewire::actingAs($admin)->test('pages::admin.user', ['user' => $user])
        ->call('openBlock', 'suspend')
        ->set('suspendFor', '7')
        ->set('blockReason', 'Checking a deposit')
        ->call('block')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->isSuspended())->toBeTrue()
        ->and($user->isBanned())->toBeFalse()
        ->and($user->suspended_until->isSameDay(now()->addDays(7)))->toBeTrue();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('suspended until')->toContain('Checking a deposit');

    $this->travel(8)->days();
    expect($user->fresh()->isBlocked())->toBeFalse();
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

test('an admin suspends a user until they lift it', function () {
    $user = User::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.user', ['user' => $user])
        ->call('openBlock', 'suspend')
        ->set('suspendFor', 'forever')
        ->call('block')
        ->assertHasNoErrors();

    $this->travel(1)->years();
    expect($user->fresh()->isSuspended())->toBeTrue()->and($user->fresh()->suspended_until)->toBeNull();
});

test('an admin bans a user with a reason and can lift the ban', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withBalance(50_000_000)->create();

    $component = Livewire::actingAs($admin)->test('pages::admin.user', ['user' => $user])
        ->call('openBlock', 'ban')
        ->call('block')
        ->assertHasErrors('blockReason')
        ->set('blockReason', 'Fraudulent deposits')
        ->call('block')
        ->assertHasNoErrors()
        ->assertSee('Banned on')
        ->assertSee('Fraudulent deposits');

    expect($user->fresh()->isBanned())->toBeTrue()
        ->and($user->fresh()->balance_micros)->toBe(50_000_000);

    $this->actingAs($user->fresh())->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'This account has been banned. Reason: Fraudulent deposits. Please contact support.']);
    $this->assertGuest();

    $this->actingAs($admin)->get(route('admin.users', ['status' => 'banned']))->assertSee($user->email);
    $this->actingAs($admin)->get(route('admin.users', ['status' => 'active']))->assertDontSee($user->email);

    $component->call('restore');
    expect($user->fresh()->isBlocked())->toBeFalse();
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

test('an admin cannot ban themselves', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test('pages::admin.user', ['user' => $admin])
        ->set('blockAction', 'ban')
        ->set('blockReason', 'Oops')
        ->call('block')
        ->assertStatus(422);

    expect($admin->fresh()->isBanned())->toBeFalse();
});

test('an admin approves a deposit from the queue with the suggested USDT amount', function () {
    $admin = User::factory()->admin()->create();
    $deposit = Deposit::factory()->create(['coin_amount' => '300']);

    Livewire::actingAs($admin)->test('pages::admin.deposits')
        ->assertSee($deposit->user->name)
        ->assertSee('300 USDT')
        ->call('startApprove', $deposit->id)
        ->assertSet('creditUsdt', '300.00')
        ->call('approve')
        ->assertHasNoErrors();

    expect($deposit->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($deposit->fresh()->credited_micros)->toBe(300_000_000)
        ->and($deposit->user->fresh()->balance_micros)->toBe(300_000_000);
});

test('an admin can change the USDT amount before crediting a deposit in another coin', function () {
    $admin = User::factory()->admin()->create();
    $btc = DepositWallet::factory()->bitcoin()->create();
    $deposit = Deposit::factory()->create(['deposit_wallet_id' => $btc->id, 'coin' => 'BTC', 'network' => 'Bitcoin', 'coin_amount' => '0.01']);

    Livewire::actingAs($admin)->test('pages::admin.deposits')
        ->call('startApprove', $deposit->id)
        ->set('creditUsdt', 'abc')
        ->call('approve')
        ->assertHasErrors('creditUsdt')
        ->set('creditUsdt', '765.40')
        ->call('approve')
        ->assertHasNoErrors();

    expect($deposit->user->fresh()->balance_micros)->toBe(765_400_000);
});

test('rejecting a withdrawal needs a reason and refunds the user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withBalance(500_000_000)->create();
    Setting::put(['withdrawal_requires_kyc' => false]);
    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 200_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

    $component = Livewire::actingAs($admin)->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'reject')
        ->call('confirm')
        ->assertHasErrors('note');

    $component->set('note', 'Address not confirmed')->call('confirm')->assertHasNoErrors();

    expect($withdrawal->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($user->fresh()->balance_micros)->toBe(500_000_000);
});

test('an admin creates a plan with exact USDT and basis point values', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.plans')
        ->call('create')
        ->set('name', 'Test Gold')
        ->set('category', 'gold')
        ->set('summary', 'A test plan.')
        ->set('minUsdt', '250')
        ->set('maxUsdt', '25000.50')
        ->set('durationDays', 45)
        ->set('targetPercent', '2.75')
        ->call('save')
        ->assertHasNoErrors();

    $plan = Plan::query()->sole();
    expect($plan->min_micros)->toBe(250_000_000)
        ->and($plan->max_micros)->toBe(25_000_500_000)
        ->and($plan->target_return_bps)->toBe(275)
        ->and($plan->category->value)->toBe('gold');
});

test('a plan maximum below its minimum is refused', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.plans')
        ->call('create')
        ->set('name', 'Bad')
        ->set('summary', 'Bad plan.')
        ->set('minUsdt', '100')
        ->set('maxUsdt', '50')
        ->call('save')
        ->assertHasErrors('maxUsdt');

    expect(Plan::count())->toBe(0);
});

test('an admin reviews identity documents', function () {
    Storage::fake('local');
    Storage::disk('local')->put('kyc/doc.png', 'image');
    $admin = User::factory()->admin()->create();
    $submission = KycSubmission::factory()->create(['document_path' => 'kyc/doc.png']);

    $this->actingAs($admin)->get(route('admin.kyc.file', [$submission, 'document']))->assertOk();
    $this->actingAs($submission->user)->get(route('admin.kyc.file', [$submission, 'document']))->assertForbidden();

    Livewire::actingAs($admin)->test('pages::admin.kyc')
        ->call('review', $submission->id, 'approve')
        ->call('confirm');

    expect($submission->fresh()->status)->toBe(KycStatus::Approved)
        ->and($submission->user->fresh()->isKycVerified())->toBeTrue();
});

test('an admin publishes an announcement', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.announcements')
        ->call('create')
        ->set('title', 'New plan available')
        ->set('body', 'Try the new gold plan.')
        ->call('save')
        ->assertHasNoErrors();

    expect(Announcement::query()->published()->sole()->title)->toBe('New plan available');
});

test('an admin saves site settings', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.settings')
        ->set('siteName', 'Hashvale Capital')
        ->set('minWithdrawal', '25.50')
        ->set('referralBonusPercent', '0.5')
        ->set('withdrawalRequiresKyc', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('site_name'))->toBe('Hashvale Capital')
        ->and(Setting::integer('min_withdrawal_micros'))->toBe(25_500_000)
        ->and(Setting::integer('referral_bonus_bps'))->toBe(50)
        ->and(Setting::enabled('withdrawal_requires_kyc'))->toBeFalse();
});

test('the create admin command makes an admin login', function () {
    $this->artisan('app:create-admin', ['--name' => 'Owner', '--email' => 'Owner@Example.com', '--password' => 'secret-password'])
        ->assertSuccessful();

    $admin = User::query()->where('email', 'owner@example.com')->sole();
    expect($admin->is_admin)->toBeTrue()->and($admin->hasVerifiedEmail())->toBeTrue();
});

test('a withdrawal payment transaction ID, when given, must be valid and the user can see it', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withBalance(500_000_000)->create();
    Setting::put(['withdrawal_requires_kyc' => false]);
    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 200_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
    $txid = str_repeat('f0', 32);

    $component = Livewire::actingAs($admin)->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'approve')
        ->assertSee('Payment transaction ID (optional)');

    $component->set('payoutTxid', 'not-a-txid')->call('confirm')->assertHasErrors('payoutTxid');
    $component->set('payoutTxid', $txid)->call('confirm')->assertHasNoErrors();

    expect($withdrawal->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($withdrawal->fresh()->payout_txid)->toBe($txid)
        ->and($user->fresh()->balance_micros)->toBe(300_000_000);

    $this->actingAs($user)->get(route('withdraw'))->assertSee('https://tronscan.org/#/transaction/'.$txid, false);
});

test('an admin adds, turns off and deletes deposit wallets for any coin', function () {
    $component = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.wallets')
        ->call('create')
        ->set('coin', 'usdt')
        ->set('network', 'ERC20')
        ->set('address', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e')
        ->set('explorerTxUrl', 'https://etherscan.io/tx/')
        ->call('save')
        ->assertHasNoErrors();

    $wallet = DepositWallet::query()->sole();
    expect($wallet->coin)->toBe('USDT')
        ->and($wallet->network)->toBe('ERC20')
        ->and($wallet->is_active)->toBeTrue();

    $component->call('toggle', $wallet->id);
    expect($wallet->fresh()->is_active)->toBeFalse();

    $component->call('delete', $wallet->id);
    expect(DepositWallet::count())->toBe(0);
});

test('a BTC deposit wallet needs a valid Bitcoin address', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.wallets')
        ->call('create')
        ->set('coin', 'BTC')
        ->set('network', 'Bitcoin')
        ->set('address', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdx')
        ->call('save')
        ->assertHasErrors('address')
        ->set('address', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')
        ->call('save')
        ->assertHasNoErrors();

    expect(DepositWallet::query()->sole()->coin)->toBe('BTC');
});

test('a wallet with deposits on record is kept for the history', function () {
    $deposit = Deposit::factory()->create();

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.wallets')
        ->call('delete', $deposit->deposit_wallet_id);

    expect(DepositWallet::query()->whereKey($deposit->deposit_wallet_id)->exists())->toBeTrue();
});

test('the admin dashboard pages through balance changes and explains an empty deposits chart', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Paged User']);

    foreach (range(1, 10) as $number) {
        LedgerEntry::factory()->for($user)->create(['description' => 'Change '.$number]);
    }

    LedgerEntry::factory()->for($user)->create(['type' => 'conversion', 'amount_micros' => 0, 'balance_after_micros' => 1_234_560_000]);

    Livewire::actingAs($admin)->test('pages::admin.dashboard')
        ->assertSee('No deposits were approved in the last 14 days.')
        ->assertSee('Converted to USDT')
        ->assertSee('1,234.56')
        ->assertSeeInOrder(['Converted to USDT', 'Balance', '1,234.56'])
        ->assertSee('wire:click="nextPage(\'changes\')"', false)
        ->call('nextPage', 'changes')
        ->assertSet('paginators.changes', 2);
});

test('large amounts wrap inside the stat cards instead of spilling out', function () {
    User::factory()->withBalance(768_919_729_970_000)->create();

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('768,919,729.97')
        ->assertSee('@container min-w-0', false)
        ->assertSee('break-words', false);
});

test('a withdrawal can be approved without a transaction ID', function () {
    $user = User::factory()->withBalance(500_000_000)->create();
    Setting::put(['withdrawal_requires_kyc' => false]);
    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 200_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'approve')
        ->call('confirm')
        ->assertHasNoErrors();

    expect($withdrawal->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($withdrawal->fresh()->payout_txid)->toBeNull()
        ->and($withdrawal->fresh()->payoutUrl())->toBeNull()
        ->and($user->notifications()->pluck('data')->pluck('title')->all())->toContain('Withdrawal requested', 'Withdrawal sent');
});

test('every admin and customer list is paginated', function () {
    $admin = User::factory()->admin()->create();
    Plan::factory()->count(16)->create();
    Announcement::factory()->count(11)->create();
    DepositWallet::factory()->count(11)->create();
    $customer = User::factory()->create();
    LedgerEntry::factory()->for($customer)->count(16)->create();
    User::factory()->count(16)->create(['referred_by_id' => $customer->id]);

    foreach (['pages::admin.plans' => [], 'pages::admin.announcements' => [], 'pages::admin.wallets' => [], 'pages::admin.user' => ['user' => $customer]] as $page => $params) {
        Livewire::actingAs($admin)->test($page, $params)->assertSee('wire:click="nextPage(\'page\')"', false);
    }

    foreach (['pages::referrals', 'pages::invest'] as $page) {
        Livewire::actingAs($customer)->test($page)->assertSee('wire:click="nextPage(\'page\')"', false);
    }
});
