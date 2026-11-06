<?php

use App\Enums\KycStatus;
use App\Enums\RequestStatus;
use App\Models\Announcement;
use App\Models\DepositWallet;
use App\Models\Investment;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('every user page opens without any demo wording', function (string $route) {
    $user = User::factory()->withBalance(1_000_000)->create(['name' => 'Ada Lovelace']);
    Investment::factory()->for($user)->for(Plan::factory()->state(['name' => 'Growth Plan', 'summary' => 'A plan with a target rate.']))->create();

    $this->actingAs($user)->get(route($route))
        ->assertOk()
        ->assertDontSee('demo', false)
        ->assertDontSee('Demo')
        ->assertDontSee('simulated');
})->with(['dashboard', 'invest', 'investments', 'deposit', 'withdraw', 'transactions', 'kyc', 'referrals', 'notifications', 'support']);

test('guests must log in, and new users must verify their email first', function () {
    $this->get(route('withdraw'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('withdraw'))->assertRedirect(route('verification.notice'));
});

test('admins are kept out of the customer area', function (string $route) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))->assertRedirect(route('admin.dashboard'));
})->with(['dashboard', 'deposit', 'invest', 'support']);

test('customers see only the customer menu', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee(route('withdraw'))
        ->assertDontSee(route('admin.dashboard'))
        ->assertDontSee('Admin panel');
});

test('the dashboard shows the plan calculator and a getting started checklist', function () {
    Plan::factory()->create(['name' => 'Bitcoin Plus']);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('Plan calculator')
        ->assertSee('data-calculator', false)
        ->assertSee('Getting started')
        ->assertSee('Make your first deposit');
});

test('the dashboard shows published announcements only', function () {
    Announcement::factory()->create(['title' => 'Maintenance on Sunday']);
    Announcement::factory()->create(['title' => 'Hidden draft', 'is_published' => false]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('Maintenance on Sunday')
        ->assertDontSee('Hidden draft');
});

test('a user picks a deposit wallet and submits the amount sent with its transaction ID', function () {
    $usdt = DepositWallet::factory()->create();
    $btc = DepositWallet::factory()->bitcoin()->create(['sort_order' => 1]);
    DepositWallet::factory()->inactive()->create(['coin' => 'DOGE', 'network' => 'Dogecoin', 'address' => 'DH5yaieqoZN36fDVciNyRueRGvGLR3mr7L']);
    $user = User::factory()->create();
    $txid = str_repeat('ab', 32);

    Livewire::actingAs($user)->test('pages::deposit')
        ->assertSet('walletId', $usdt->id)
        ->assertSee($usdt->address)
        ->assertDontSee('Dogecoin')
        ->set('walletId', $btc->id)
        ->assertSee('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')
        ->set('amount', '0.05')
        ->set('txid', ' '.$txid.' ')
        ->call('save')
        ->assertHasNoErrors();

    $deposit = $user->deposits()->sole();
    expect($deposit->coin)->toBe('BTC')
        ->and($deposit->network)->toBe('Bitcoin')
        ->and($deposit->coin_amount)->toBe('0.05')
        ->and($deposit->credited_micros)->toBeNull()
        ->and($deposit->reference)->toBe($txid)
        ->and($deposit->status)->toBe(RequestStatus::Pending)
        ->and($user->fresh()->balance_micros)->toBe(0);

    Livewire::actingAs($user)->test('pages::deposit')
        ->set('amount', '0.05')
        ->set('txid', $txid)
        ->call('save')
        ->assertHasErrors(['txid' => 'unique']);
});

test('the deposit page shows the USDT value of what the user typed', function () {
    DepositWallet::factory()->bitcoin()->create();
    Cache::put('market-prices', ['btc_cents' => 7_000_000, 'updated_at' => now()->toIso8601String()]);

    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->set('amount', '0.01')
        ->assertSee('About 700.00 USDT');
});

test('deposits need a valid amount and transaction ID', function (string $amount, string $txid, string $field) {
    DepositWallet::factory()->create();

    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->set('amount', $amount)
        ->set('txid', $txid)
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'empty amount' => ['', str_repeat('a', 64), 'amount'],
    'text amount' => ['abc', str_repeat('a', 64), 'amount'],
    'too many decimals' => ['0.123456789', str_repeat('a', 64), 'amount'],
    'missing txid' => ['100', '', 'txid'],
    'short txid' => ['100', 'abc123', 'txid'],
    'txid with symbols' => ['100', str_repeat('a', 30).'!@#', 'txid'],
]);

test('deposits are closed until the admin adds a deposit wallet', function () {
    DepositWallet::factory()->inactive()->create();

    Livewire::actingAs($user = User::factory()->create())->test('pages::deposit')
        ->assertSee('Deposits are not open yet')
        ->set('amount', '100')
        ->set('txid', str_repeat('a', 64))
        ->call('save')
        ->assertHasErrors('walletId');

    expect($user->deposits()->count())->toBe(0);
});

test('a verified user can request a USDT withdrawal on TRC20 or ERC20', function (string $network, string $address) {
    $user = User::factory()->withBalance(1_000_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();

    Livewire::actingAs($user)->test('pages::withdraw')
        ->set('amount', '400')
        ->set('network', $network)
        ->set('address', ' '.$address.' ')
        ->set('confirmAddress', true)
        ->call('save')
        ->assertHasNoErrors();

    $withdrawal = $user->withdrawals()->sole();
    expect($user->fresh()->balance_micros)->toBe(600_000_000)
        ->and($withdrawal->network->value)->toBe($network)
        ->and($withdrawal->address)->toBe($address);
})->with([
    'TRC20' => ['trc20', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'],
    'ERC20' => ['erc20', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'],
]);

test('withdrawals to an invalid address, the wrong network or above the balance are refused', function (string $amount, string $network, string $address, string $errorField) {
    $user = User::factory()->withBalance(1_000_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();

    Livewire::actingAs($user)->test('pages::withdraw')
        ->set('amount', $amount)
        ->set('network', $network)
        ->set('address', $address)
        ->set('confirmAddress', true)
        ->call('save')
        ->assertHasErrors($errorField);

    expect($user->fresh()->balance_micros)->toBe(1_000_000_000);
})->with([
    'typo in TRC20 address' => ['100', 'trc20', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6u', 'address'],
    'ERC20 address on TRC20' => ['100', 'trc20', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'address'],
    'Bitcoin address' => ['100', 'erc20', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', 'address'],
    'unknown network' => ['100', 'dogecoin', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', 'network'],
    'too many decimals' => ['100.123', 'trc20', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'amount'],
    'more than balance' => ['1000.01', 'trc20', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'amount'],
]);

test('a user can join a plan after accepting the risks', function () {
    $user = User::factory()->withBalance(500_000_000)->create();
    $plan = Plan::factory()->create(['min_micros' => 100_000_000, 'target_return_bps' => 300]);

    $component = Livewire::actingAs($user)->test('pages::invest')
        ->call('choose', $plan->id)
        ->set('amount', '200')
        ->call('invest')
        ->assertHasErrors('acceptRisk');

    $component->assertSee('Profit at the end')->assertSee('6.00')->assertSee('206.00');

    $component->set('acceptRisk', true)->call('invest')->assertHasNoErrors()->assertRedirect(route('investments'));

    expect($user->investments()->sole()->amount_micros)->toBe(200_000_000)
        ->and($user->fresh()->balance_micros)->toBe(300_000_000);
});

test('a user can send identity documents, which are stored privately', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::kyc')
        ->set('fullName', 'Emma Carter')
        ->set('documentType', 'passport')
        ->set('document', UploadedFile::fake()->image('passport.jpg'))
        ->set('selfie', UploadedFile::fake()->image('selfie.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $submission = $user->kycSubmissions()->sole();
    expect($submission->status)->toBe(KycStatus::Pending);
    Storage::disk('local')->assertExists([$submission->document_path, $submission->selfie_path]);
    expect($submission->document_path)->toStartWith('kyc/'.$user->id.'/');
});

test('a user cannot send new documents while a check is pending', function () {
    $user = User::factory()->create();
    KycSubmission::factory()->for($user)->create();

    Livewire::actingAs($user)->test('pages::kyc')
        ->set('document', UploadedFile::fake()->image('passport.jpg'))
        ->set('selfie', UploadedFile::fake()->image('selfie.jpg'))
        ->call('save')
        ->assertForbidden();
});

test('deleting an account removes its identity documents', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Storage::disk('local')->put('kyc/'.$user->id.'/document.jpg', 'x');

    $user->delete();

    Storage::disk('local')->assertMissing('kyc/'.$user->id.'/document.jpg');
});

test('a suspended user is logged out', function () {
    $user = User::factory()->suspended()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
