<?php

use App\Enums\WithdrawalNetwork;
use App\Exceptions\WalletException;
use App\Models\KycSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Notifications\WithdrawalCodeNotification;
use App\Services\WalletService;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

const SAFE_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

function withdrawingUser(array $attributes = []): User
{
    $user = User::factory()->withBalance(1_000_000_000)->create($attributes);
    KycSubmission::factory()->approved()->for($user)->create();

    return $user;
}

function fillWithdrawal(User $user): Testable
{
    return Livewire::actingAs($user)->test('pages::withdraw')
        ->set('amount', '100')
        ->set('network', 'trc20')
        ->set('address', SAFE_ADDRESS)
        ->set('confirmAddress', true);
}

test('a withdrawal needs the code emailed to the user, and a wrong code holds nothing', function () {
    Notification::fake();
    $user = withdrawingUser();

    fillWithdrawal($user)->call('save')->assertHasErrors('securityCode');

    $component = fillWithdrawal($user)->call('sendCode')->set('securityCode', '000000');
    $code = collect(Notification::sent($user, WithdrawalCodeNotification::class))->last()->code;

    if ($code !== '000000') {
        $component->call('save')->assertHasErrors('securityCode');
        expect($user->withdrawals()->count())->toBe(0);
    }

    $component->set('securityCode', $code)->call('save')->assertHasNoErrors();

    expect($user->withdrawals()->count())->toBe(1)
        ->and($user->fresh()->balance_micros)->toBe(900_000_000);
});

test('an emailed code works once only', function () {
    Notification::fake();
    $user = withdrawingUser();

    $component = fillWithdrawal($user)->call('sendCode');
    $code = collect(Notification::sent($user, WithdrawalCodeNotification::class))->last()->code;
    $component->set('securityCode', $code)->call('save')->assertHasNoErrors();

    fillWithdrawal($user)->set('securityCode', $code)->call('save')->assertHasErrors('securityCode');
    expect($user->withdrawals()->count())->toBe(1);
});

test('users with two-step login confirm with their authenticator app instead of email', function () {
    Notification::fake();
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();
    $user = withdrawingUser(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()]);

    fillWithdrawal($user)
        ->assertSee('Code from your authenticator app')
        ->assertDontSee('Email me a code')
        ->set('securityCode', $google2fa->getCurrentOtp($secret))
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNotSentTo($user, WithdrawalCodeNotification::class);
    expect($user->withdrawals()->count())->toBe(1);
});

test('withdrawals are paused for 24 hours after a password change', function () {
    $user = withdrawingUser();
    $user->update(['password' => 'a-new-password']);

    expect($user->fresh()->password_changed_at)->not->toBeNull()
        ->and(fn () => app(WalletService::class)->requestWithdrawal($user->fresh(), 100_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS))
        ->toThrow(WalletException::class, 'paused for 24 hours after a password change');

    $this->travel(25)->hours();

    expect(app(WalletService::class)->requestWithdrawal($user->fresh(), 100_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS)->exists)->toBeTrue();
});

test('the daily withdrawal limit counts today\'s requests but not rejected ones', function () {
    Notification::fake();
    Setting::put(['daily_withdrawal_limit_micros' => 250_000_000]);
    $user = withdrawingUser();
    $wallet = app(WalletService::class);

    $first = $wallet->requestWithdrawal($user, 200_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS);

    expect(fn () => $wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS))
        ->toThrow(WalletException::class, 'You can still withdraw 50.00 USDT today.');

    $wallet->rejectWithdrawal($first, User::factory()->admin()->create(), 'Wrong address');
    expect($wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS)->exists)->toBeTrue();

    $this->travel(1)->days();
    expect($wallet->requestWithdrawal($user, 150_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS)->exists)->toBeTrue();
});

test('admins set the daily limit, and leaving it empty means no limit', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.settings')
        ->set('dailyWithdrawalLimit', '5000')
        ->call('save')
        ->assertHasNoErrors();
    expect(Setting::integer('daily_withdrawal_limit_micros'))->toBe(5_000_000_000);

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.settings')
        ->set('dailyWithdrawalLimit', '')
        ->call('save');
    expect(Setting::integer('daily_withdrawal_limit_micros'))->toBe(0);
});

test('the user is warned by email the first time an address is used, not after', function () {
    Notification::fake();
    $user = withdrawingUser();
    $wallet = app(WalletService::class);

    $wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS);
    $wallet->requestWithdrawal($user, 100_000_000, WithdrawalNetwork::Trc20, SAFE_ADDRESS);

    Notification::assertSentToTimes($user, AccountActivity::class, 3);
    expect(collect(Notification::sent($user, AccountActivity::class))->where('title', 'New withdrawal address used'))->toHaveCount(1);
});
