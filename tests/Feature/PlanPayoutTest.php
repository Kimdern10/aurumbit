<?php

use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Services\WalletService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function finishedPlan(User $user): Investment
{
    Notification::fake();
    $investment = app(WalletService::class)->invest($user, Plan::factory()->create(['name' => 'Steady Plan', 'min_micros' => 1_000_000, 'target_return_bps' => 900, 'duration_days' => 30]), 100_000_000);

    test()->travel(31)->days();
    app(WalletService::class)->settle($investment->fresh());

    return $investment->fresh();
}

test('a completed plan has a receipt showing capital and profit, linked from the email', function () {
    $user = User::factory()->withBalance(100_000_000)->create();
    $investment = finishedPlan($user);

    $sent = collect(Notification::sent($user, AccountActivity::class))->firstWhere('title', 'Plan completed');
    expect($sent->url)->toBe($investment->receiptUrl())
        ->and($sent->body)->toContain($investment->code());

    $this->actingAs($user)->get($investment->receiptUrl())
        ->assertOk()
        ->assertSee('Plan receipt')
        ->assertSee($investment->code())
        ->assertSee('Steady Plan')
        ->assertSee('109.00 USDT')
        ->assertSee('9.00 USDT')
        ->assertSee('Back to investments');

    $this->actingAs($user)->get(route('investments'))->assertSee($investment->receiptUrl(), false);
});

test('users cannot open someone else\'s plan receipt, but admins can', function () {
    $investment = finishedPlan(User::factory()->withBalance(100_000_000)->create());

    $this->actingAs(User::factory()->create())->get($investment->receiptUrl())->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get($investment->receiptUrl(forAdmin: true))->assertOk();
});

test('the dashboard shows the next payout and how earnings built up', function () {
    Notification::fake();
    $user = User::factory()->withBalance(500_000_000)->create();
    $wallet = app(WalletService::class);
    $wallet->invest($user, Plan::factory()->create(['name' => 'Long Plan', 'min_micros' => 1_000_000, 'target_return_bps' => 1000, 'duration_days' => 60]), 100_000_000);
    $wallet->invest($user, Plan::factory()->create(['name' => 'Short Plan', 'min_micros' => 1_000_000, 'target_return_bps' => 500, 'duration_days' => 10]), 200_000_000);

    $this->travel(3)->days();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Next payout')
        ->assertSeeInOrder(['data-next-payout', 'Short Plan', '210.00', 'You have 1 more plan running.'], false)
        ->assertSee('Earnings growth');

    $points = Livewire::actingAs($user)->test('pages::dashboard')->instance()->earningsHistory;
    expect($points)->toHaveCount(4)
        ->and($points[0]['value'])->toBe(0)
        ->and(end($points)['value'])->toBe(3_000_000 + 500_000);
});

test('the dashboard explains the next payout card before any plan', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('No plan is running')
        ->assertSee('Your earnings will build up here');
});
