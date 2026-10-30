<?php

use App\Enums\KycStatus;
use App\Enums\RequestStatus;
use App\Models\Announcement;
use App\Models\Deposit;
use App\Models\KycSubmission;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

$adminRoutes = ['admin.dashboard', 'admin.users', 'admin.plans', 'admin.deposits', 'admin.withdrawals', 'admin.investments', 'admin.kyc', 'admin.announcements', 'admin.tickets', 'admin.ledger', 'admin.settings'];

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
    $user = User::factory()->withBalance(100_000)->create();

    $this->actingAs($admin)->get(route('admin.users.show', $user))->assertOk()->assertSee($user->email);

    Livewire::actingAs($admin)->test('pages::admin.user', ['user' => $user])
        ->set('adjustAmount', '-0.0004')
        ->set('adjustReason', 'Duplicate credit')
        ->call('adjust')
        ->assertHasNoErrors();

    expect($user->fresh()->balance_sats)->toBe(60_000)
        ->and($user->ledgerEntries()->sole()->description)->toBe('Duplicate credit');

    $this->actingAs($user)->get(route('transactions'))
        ->assertSee('Balance adjustment')
        ->assertSee('Duplicate credit')
        ->assertDontSee('Admin')
        ->assertDontSee($admin->name);
});

test('an admin can suspend a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    Livewire::actingAs($admin)->test('pages::admin.user', ['user' => $user])->call('toggleSuspension');

    expect($user->fresh()->is_suspended)->toBeTrue();
});

test('an admin approves a deposit from the queue', function () {
    $admin = User::factory()->admin()->create();
    $deposit = Deposit::factory()->create(['amount_sats' => 300_000]);

    Livewire::actingAs($admin)->test('pages::admin.deposits')
        ->assertSee($deposit->user->name)
        ->call('approve', $deposit->id);

    expect($deposit->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($deposit->user->fresh()->balance_sats)->toBe(300_000);
});

test('rejecting a withdrawal needs a reason and refunds the user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withBalance(500_000)->create();
    Setting::put(['withdrawal_requires_kyc' => false]);
    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 200_000, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');

    $component = Livewire::actingAs($admin)->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'reject')
        ->call('confirm')
        ->assertHasErrors('note');

    $component->set('note', 'Address not confirmed')->call('confirm')->assertHasNoErrors();

    expect($withdrawal->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and($user->fresh()->balance_sats)->toBe(500_000);
});

test('an admin creates a plan with exact satoshi and basis point values', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.plans')
        ->call('create')
        ->set('name', 'Test Gold')
        ->set('category', 'gold')
        ->set('summary', 'A test plan.')
        ->set('minBtc', '0.005')
        ->set('maxBtc', '0.5')
        ->set('durationDays', 45)
        ->set('targetPercent', '2.75')
        ->call('save')
        ->assertHasNoErrors();

    $plan = Plan::query()->sole();
    expect($plan->min_sats)->toBe(500_000)
        ->and($plan->max_sats)->toBe(50_000_000)
        ->and($plan->target_return_bps)->toBe(275)
        ->and($plan->category->value)->toBe('gold');
});

test('a plan maximum below its minimum is refused', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.plans')
        ->call('create')
        ->set('name', 'Bad')
        ->set('summary', 'Bad plan.')
        ->set('minBtc', '1')
        ->set('maxBtc', '0.5')
        ->call('save')
        ->assertHasErrors('maxBtc');

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
        ->set('minWithdrawal', '0.0002')
        ->set('referralBonusPercent', '0.5')
        ->set('withdrawalRequiresKyc', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('site_name'))->toBe('Hashvale Capital')
        ->and(Setting::integer('min_withdrawal_sats'))->toBe(20_000)
        ->and(Setting::integer('referral_bonus_bps'))->toBe(50)
        ->and(Setting::enabled('withdrawal_requires_kyc'))->toBeFalse();
});

test('the create admin command makes an admin login', function () {
    $this->artisan('app:create-admin', ['--name' => 'Owner', '--email' => 'Owner@Example.com', '--password' => 'secret-password'])
        ->assertSuccessful();

    $admin = User::query()->where('email', 'owner@example.com')->sole();
    expect($admin->is_admin)->toBeTrue()->and($admin->hasVerifiedEmail())->toBeTrue();
});

test('approving a withdrawal needs the payment transaction ID, which the user can see', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->withBalance(500_000)->create();
    Setting::put(['withdrawal_requires_kyc' => false]);
    $withdrawal = app(WalletService::class)->requestWithdrawal($user, 200_000, 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');
    $txid = str_repeat('f0', 32);

    $component = Livewire::actingAs($admin)->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'approve')
        ->call('confirm')
        ->assertHasErrors('payoutTxid');

    $component->set('payoutTxid', 'not-a-txid')->call('confirm')->assertHasErrors('payoutTxid');
    $component->set('payoutTxid', $txid)->call('confirm')->assertHasNoErrors();

    expect($withdrawal->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($withdrawal->fresh()->payout_txid)->toBe($txid)
        ->and($user->fresh()->balance_sats)->toBe(300_000);

    $this->actingAs($user)->get(route('withdraw'))->assertSee('https://mempool.space/tx/'.$txid, false);
});

test('the deposit address must be a valid Bitcoin address', function () {
    $component = Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.settings')
        ->set('depositAddress', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdx')
        ->call('save')
        ->assertHasErrors('depositAddress');

    $component->set('depositAddress', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')->call('save')->assertHasNoErrors();

    expect(Setting::get('deposit_address'))->toBe('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');
});
