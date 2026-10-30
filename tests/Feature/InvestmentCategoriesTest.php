<?php

use App\Enums\PlanCategory;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('users can invest in every kind of plan, including gold', function (PlanCategory $category) {
    $user = User::factory()->withBalance(5_000_000)->create();
    $plan = Plan::factory()->create(['category' => $category, 'min_sats' => 100_000]);

    Livewire::actingAs($user)->test('pages::invest')
        ->call('choose', $plan->id)
        ->set('amount', '0.01')
        ->set('acceptRisk', true)
        ->call('invest')
        ->assertHasNoErrors();

    expect(Investment::query()->sole()->plan->category)->toBe($category)
        ->and($user->fresh()->balance_sats)->toBe(4_000_000);
})->with(PlanCategory::cases());

test('gold plans show and record the gold value of the investment', function () {
    Cache::put('market-prices', ['btc_cents' => 6_000_000, 'gold_cents' => 250_000, 'updated_at' => now()->toIso8601String()], 300);
    $user = User::factory()->withBalance(100_000_000)->create();
    $plan = Plan::factory()->gold()->create(['min_sats' => 100_000, 'max_sats' => null]);

    Livewire::actingAs($user)->test('pages::invest')
        ->call('choose', $plan->id)
        ->set('amount', '1')
        ->assertSee('24.000 oz')
        ->set('acceptRisk', true)
        ->call('invest');

    expect(Investment::query()->sole()->gold_mg)->toBe(746_483);
});

test('the plans page can be filtered by category', function () {
    Plan::factory()->create(['name' => 'Hash Plan', 'category' => PlanCategory::Bitcoin]);
    Plan::factory()->gold()->create(['name' => 'Gold Plan']);
    Plan::factory()->create(['name' => 'Stake Plan', 'category' => PlanCategory::Ethereum]);

    $component = Livewire::actingAs(User::factory()->create())->test('pages::invest')
        ->assertSee(['All plans', 'Bitcoin mining', 'Gold mining', 'Ethereum staking'])
        ->assertDontSee('Solana staking');

    $component->set('category', 'gold');

    expect($component->get('shownPlans')->pluck('name')->all())->toBe(['Gold Plan']);
});

test('the landing page lists the new crypto plans with live prices', function () {
    Plan::factory()->create(['name' => 'Ether Stake', 'category' => PlanCategory::Ethereum]);
    Plan::factory()->create(['name' => 'Top 10 Index', 'category' => PlanCategory::CryptoIndex]);

    $this->get(route('home'))
        ->assertSee('Ethereum staking')
        ->assertSee('Crypto index')
        ->assertSee('Ether Stake')
        ->assertSee('data-price="eth"', false)
        ->assertSee('data-price="sol"', false);
});

test('admins filter plans by category and create one for that category', function () {
    $admin = User::factory()->admin()->create();
    Plan::factory()->create(['name' => 'Hash Plan']);
    Plan::factory()->gold()->create(['name' => 'Gold Plan']);

    Livewire::actingAs($admin)->test('pages::admin.plans')
        ->set('filter', 'gold')
        ->assertSee('Gold Plan')
        ->assertDontSee('Hash Plan')
        ->call('create')
        ->assertSet('category', 'gold')
        ->set('name', 'Solana Max')
        ->set('category', 'solana')
        ->set('summary', 'Solana staking term.')
        ->set('minBtc', '0.01')
        ->call('save')
        ->assertHasNoErrors();

    expect(Plan::query()->where('name', 'Solana Max')->sole()->category)->toBe(PlanCategory::Solana);
});

test('admins can delete unused plans but only close plans with investments', function () {
    $admin = User::factory()->admin()->create();
    $unused = Plan::factory()->create();
    $used = Investment::factory()->create()->plan;

    Livewire::actingAs($admin)->test('pages::admin.plans')
        ->call('delete', $unused->id)
        ->call('delete', $used->id);

    expect(Plan::query()->find($unused->id))->toBeNull()
        ->and(Plan::query()->find($used->id))->not->toBeNull();
});

test('public pages use the new brand and contain nothing Nigeria-specific', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('Hashvale')
        ->assertDontSee('AurumBit')
        ->assertDontSee('Nigeria');
})->with(['home', 'legal.terms', 'legal.risk', 'legal.privacy', 'register']);

test('plan pages show rates without risk labels', function () {
    Plan::factory()->create(['name' => 'Hash Plan', 'target_return_bps' => 350, 'duration_days' => 30, 'risk_level' => 'very_high']);

    $this->actingAs(User::factory()->create())->get(route('invest'))
        ->assertSee('Hash Plan')
        ->assertSee('3.5%')
        ->assertDontSee('Very high risk');
});
