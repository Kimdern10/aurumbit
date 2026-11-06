<?php

use App\Models\Plan;
use App\Models\User;
use App\Services\MarketPrices;
use Illuminate\Support\Facades\Http;

test('the home page lists active plans with risk wording and never waits on the price API', function () {
    Http::preventStrayRequests();
    Plan::factory()->create(['name' => 'Bitcoin Plus']);
    Plan::factory()->inactive()->create(['name' => 'Closed Plan']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Bitcoin Plus')
        ->assertDontSee('Closed Plan')
        ->assertSee('How much will I earn?', false)
        ->assertSee('data-calculator', false)
        ->assertSee('Choose your plan')
        ->assertDontSee('High risk')
        ->assertDontSee('Demo')
        ->assertDontSee('simulated');
});

test('legal pages are public', function (string $route, string $heading) {
    $this->get(route($route))->assertOk()->assertSee($heading);
})->with([
    ['legal.risk', 'Risk disclosure'],
    ['legal.terms', 'Terms of use'],
    ['legal.privacy', 'Privacy policy'],
]);

test('the sitemap and robots file are served', function () {
    $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(route('legal.risk'));
    $this->get(route('robots'))->assertOk()->assertSee('Disallow: /admin');
});

test('prices come from CoinGecko and are cached', function () {
    Http::fake([
        'api.coingecko.com/*' => Http::response([
            'bitcoin' => ['usd' => 65000.5, 'usd_24h_change' => 1.234],
            'pax-gold' => ['usd' => 2650.25, 'usd_24h_change' => -0.5],
        ]),
    ]);

    $this->getJson(route('prices'))->assertOk()->assertJson([
        'btc_cents' => 6500050,
        'btc_change' => 1.23,
        'gold_cents' => 265025,
        'gold_change' => -0.5,
    ]);
    $this->getJson(route('prices'))->assertOk();

    Http::assertSentCount(1);
});

test('the last good prices are kept when the API fails', function () {
    Http::fakeSequence('api.coingecko.com/*')
        ->push(['bitcoin' => ['usd' => 60000], 'pax-gold' => ['usd' => 2500]])
        ->whenEmpty(Http::response('error', 500));

    $prices = app(MarketPrices::class);
    $prices->current();
    cache()->forget('market-prices');

    expect($prices->current()['btc_cents'])->toBe(6_000_000)
        ->and($prices->goldMilligrams(60_000_000_000))->toBe(746_483);
});

test('registration needs the terms box and records who invited the user', function () {
    $referrer = User::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('terms');

    $this->post(route('register.store'), [
        'name' => 'New Person',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
        'ref' => strtolower($referrer->referral_code),
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'new@example.com')->sole();
    expect($user->referred_by_id)->toBe($referrer->id)
        ->and($user->referral_code)->toHaveLength(8)
        ->and($user->is_admin)->toBeFalse()
        ->and($user->balance_micros)->toBe(0);
});

test('signed-in customers and admins can view the website, with links back to their area', function () {
    $customer = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($customer)->get(route('home'))
        ->assertOk()
        ->assertSee('Choose a plan')
        ->assertSee('href="'.route('invest').'"', false)
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertDontSee('Create free account');

    $this->actingAs($admin)->get(route('home'))
        ->assertOk()
        ->assertSee('Admin panel')
        ->assertSee('href="'.route('admin.dashboard').'"', false)
        ->assertDontSee('href="'.route('register').'"', false);

    foreach (['terms' => 'legal.terms', 'privacy' => 'legal.privacy', 'risk' => 'legal.risk'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});

test('the dashboard and admin panel link to the website', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('View website')
        ->assertSee('data-view-website', false);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertSee('View website')
        ->assertSee('data-view-website', false);
});
