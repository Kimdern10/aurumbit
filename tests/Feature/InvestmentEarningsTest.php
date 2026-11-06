<?php

use App\Enums\InvestmentStatus;
use App\Models\Investment;
use App\Models\User;

test('customers see what they have earned so far even before the scheduled job runs', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    Investment::factory()->for($user)->create([
        'amount_micros' => 50_000_000,
        'target_return_bps' => 80,
        'duration_days' => 14,
        'earned_micros' => 0,
        'starts_at' => now()->subDays(2)->subHour(),
        'ends_at' => now()->addDays(12)->subHour(),
    ]);

    $this->actingAs($user)->get(route('investments'))
        ->assertOk()
        ->assertSee('0.0571')
        ->assertSee('of 0.40 USDT profit')
        ->assertSee('+0.0285 USDT a day');
});

test('opening the site brings the customer\'s plans up to date without the scheduler', function () {
    $user = User::factory()->withBalance(0)->create();
    $running = Investment::factory()->for($user)->create(['amount_micros' => 1_000_000_000, 'target_return_bps' => 300, 'duration_days' => 30, 'starts_at' => now()->subDays(10)->subHour(), 'ends_at' => now()->addDays(20)]);
    $finished = Investment::factory()->for($user)->create(['amount_micros' => 1_000_000_000, 'target_return_bps' => 300, 'duration_days' => 30, 'starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);
    $someoneElses = Investment::factory()->create(['starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($running->fresh()->earned_micros)->toBe(10_000_000)
        ->and($finished->fresh()->status)->toBe(InvestmentStatus::Completed)
        ->and($user->fresh()->balance_micros)->toBe(1_030_000_000)
        ->and($someoneElses->fresh()->status)->toBe(InvestmentStatus::Active);
});

test('the catch-up runs at most once a minute per customer', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'));

    $finished = Investment::factory()->for($user)->create(['starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);
    $this->actingAs($user)->get(route('dashboard'));
    expect($finished->fresh()->status)->toBe(InvestmentStatus::Active);

    $this->travel(61)->seconds();
    $this->actingAs($user)->get(route('dashboard'));
    expect($finished->fresh()->status)->toBe(InvestmentStatus::Completed);
});

test('an admin visit pays out every customer\'s finished plans', function () {
    $finished = Investment::factory()->create(['starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk();

    expect($finished->fresh()->status)->toBe(InvestmentStatus::Completed);
});
