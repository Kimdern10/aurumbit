<?php

use App\Enums\WithdrawalNetwork;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Notifications\AccountActivityEmail;
use App\Services\WalletService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Titles of the account emails a user has been sent so far.
 *
 * @return list<string>
 */
function emailedTitles(User $user): array
{
    return collect(Notification::sent($user, AccountActivityEmail::class))->pluck('title')->all();
}

beforeEach(function () {
    Notification::fake();
    $this->wallet = app(WalletService::class);
    $this->admin = User::factory()->admin()->create();
});

test('every account notification is also emailed', function () {
    $user = User::factory()->create();

    foreach (['Deposit received', 'Deposit credited', 'Withdrawal requested', 'Withdrawal sent', 'Plan started', 'Plan cancelled', 'Balance adjusted'] as $title) {
        event(new NotificationSent($user, new AccountActivity($title, 'Body'), 'database'));
    }

    expect(emailedTitles($user))->toBe(['Deposit received', 'Deposit credited', 'Withdrawal requested', 'Withdrawal sent', 'Plan started', 'Plan cancelled', 'Balance adjusted']);
});

test('real deposits send both the in-app notification and the email', function () {
    Notification::swap(new ChannelManager(app()));
    Mail::fake();
    $user = User::factory()->create();

    $this->wallet->requestDeposit($user, DepositWallet::factory()->create(), '500');

    expect($user->notifications()->sole()->data['title'])->toBe('Deposit received');
    Mail::assertNothingQueued();
});

test('every money event creates an account notification with the reference number', function () {
    $user = User::factory()->withBalance(2_000_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();

    $deposit = $this->wallet->requestDeposit($user, DepositWallet::factory()->create(), '500');
    $withdrawal = $this->wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
    $this->wallet->invest($user, Plan::factory()->create(['min_micros' => 1_000_000]), 200_000_000);
    $this->wallet->adjust($user, -5_000_000, 'Fee correction', $this->admin);

    $sent = collect(Notification::sent($user, AccountActivity::class));

    expect($sent->pluck('title')->all())->toBe(['Deposit received', 'Withdrawal requested', 'Plan started', 'Wallet debited'])
        ->and($sent->firstWhere('title', 'Deposit received')->body)->toContain($deposit->code())
        ->and($sent->firstWhere('title', 'Withdrawal requested')->body)->toContain($withdrawal->code());
});

test('the email shows the message and a link back to the account', function () {
    $user = User::factory()->create();

    $mail = (new AccountActivityEmail('Deposit credited', 'Your 500 USDT deposit was credited.', route('history'), 'success'))->toMail($user);

    expect($mail->subject)->toContain('Deposit credited')
        ->and($mail->introLines)->toContain('Your 500 USDT deposit was credited.')
        ->and($mail->actionUrl)->toBe(route('history'));
});

test('users can turn the emails off and still see notifications in their account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::notifications')
        ->assertSet('emailNotifications', true)
        ->set('emailNotifications', false);

    expect($user->fresh()->email_notifications)->toBeFalse();

    event(new NotificationSent($user->fresh(), new AccountActivity('Deposit credited', 'Body'), 'database'));

    expect(emailedTitles($user))->toBe([]);
});

test('a mail failure never undoes or blocks a balance change', function () {
    Notification::swap(new ChannelManager(app()));
    config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
    $user = User::factory()->create();
    $deposit = Deposit::factory()->for($user)->create(['coin_amount' => '100']);

    $this->wallet->approveDeposit($deposit, $this->admin, 100_000_000);

    expect($user->fresh()->balance_micros)->toBe(100_000_000)
        ->and($deposit->fresh()->status->value)->toBe('approved')
        ->and($user->notifications()->sole()->data['title'])->toBe('Deposit credited');
});

test('admins email users when they suspend, ban or restore them', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)->test('pages::admin.user', ['user' => $user])
        ->call('openBlock', 'ban')
        ->set('blockReason', 'Fraud')
        ->call('block')
        ->call('restore');

    expect(collect(Notification::sent($user, AccountActivity::class))->pluck('title')->all())->toBe(['Account banned', 'Account restored']);
});

test('the history page lists deposits and withdrawals together with reference numbers', function () {
    $user = User::factory()->withBalance(1_000_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();
    $deposit = $this->wallet->requestDeposit($user, DepositWallet::factory()->create(), '250');
    $this->travel(1)->minutes();
    $withdrawal = $this->wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
    Deposit::factory()->create(['coin_amount' => '999']);

    Livewire::actingAs($user)->test('pages::transactions')
        ->assertSeeInOrder([$withdrawal->code(), $deposit->code()])
        ->assertSee('250 USDT')
        ->assertDontSee('999 USDT')
        ->set('kind', 'deposits')
        ->assertSee($deposit->code())
        ->assertDontSee($withdrawal->code())
        ->set('kind', 'withdrawals')
        ->assertSee($withdrawal->code())
        ->assertDontSee($deposit->code())
        ->set('kind', 'all')
        ->set('status', 'approved')
        ->assertSee('No deposits or withdrawals yet.');

    $this->actingAs($user)->get(route('history'))->assertRedirect(route('transactions', ['view' => 'requests']));
});

test('the history page is paginated', function () {
    $user = User::factory()->create();
    Deposit::factory()->for($user)->count(16)->create();

    Livewire::actingAs($user)->test('pages::transactions')
        ->assertSee('wire:click="nextPage(\'page\')"', false);
});
