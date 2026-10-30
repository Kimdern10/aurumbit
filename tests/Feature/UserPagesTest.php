<?php

use App\Enums\KycStatus;
use App\Enums\RequestStatus;
use App\Models\Announcement;
use App\Models\Investment;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('every user page opens without any demo wording', function (string $route) {
    $user = User::factory()->withBalance(1_000_000)->create();
    Investment::factory()->for($user)->create();

    $this->actingAs($user)->get(route($route))
        ->assertOk()
        ->assertDontSee('demo', false)
        ->assertDontSee('Demo')
        ->assertDontSee('simulated');
})->with(['dashboard', 'invest', 'investments', 'deposit', 'withdraw', 'transactions', 'kyc', 'referrals', 'notifications', 'support']);

test('guests must log in, and new users do not need an email code', function () {
    $this->get(route('withdraw'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('withdraw'))->assertOk();
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
        ->assertSee('BTC plan calculator')
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

test('a user can submit a deposit with its transaction ID', function () {
    Setting::put(['deposit_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq']);
    $user = User::factory()->create();
    $txid = str_repeat('AB', 32);

    Livewire::actingAs($user)->test('pages::deposit')
        ->assertSee('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq')
        ->set('amount', '0.05')
        ->set('txid', ' '.$txid.' ')
        ->call('save')
        ->assertHasNoErrors();

    $deposit = $user->deposits()->sole();
    expect($deposit->amount_sats)->toBe(5_000_000)
        ->and($deposit->reference)->toBe(strtolower($txid))
        ->and($deposit->status)->toBe(RequestStatus::Pending)
        ->and($user->fresh()->balance_sats)->toBe(0);

    Livewire::actingAs($user)->test('pages::deposit')
        ->set('amount', '0.05')
        ->set('txid', $txid)
        ->call('save')
        ->assertHasErrors(['txid' => 'unique']);
});

test('deposits need a valid amount and transaction ID', function (string $amount, string $txid, string $field) {
    Setting::put(['deposit_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq']);

    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->set('amount', $amount)
        ->set('txid', $txid)
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'empty amount' => ['', str_repeat('a', 64), 'amount'],
    'text amount' => ['abc', str_repeat('a', 64), 'amount'],
    'too many decimals' => ['0.123456789', str_repeat('a', 64), 'amount'],
    'missing txid' => ['0.01', '', 'txid'],
    'short txid' => ['0.01', 'abc123', 'txid'],
    'txid with bad letters' => ['0.01', str_repeat('z', 64), 'txid'],
]);

test('deposits are closed until the admin sets a deposit address', function () {
    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->assertSee('Deposits are not open yet')
        ->set('amount', '0.05')
        ->set('txid', str_repeat('a', 64))
        ->call('save')
        ->assertForbidden();
});

test('a verified user can request a withdrawal to a valid address', function () {
    $user = User::factory()->withBalance(1_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();

    Livewire::actingAs($user)->test('pages::withdraw')
        ->set('amount', '0.004')
        ->set('address', ' bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq ')
        ->set('confirmAddress', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->balance_sats)->toBe(600_000)
        ->and($user->withdrawals()->sole()->btc_address)->toBe('bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq');
});

test('withdrawals to an invalid address or above the balance are refused', function (string $amount, string $address, string $errorField) {
    $user = User::factory()->withBalance(1_000_000)->create();
    KycSubmission::factory()->approved()->for($user)->create();

    Livewire::actingAs($user)->test('pages::withdraw')
        ->set('amount', $amount)
        ->set('address', $address)
        ->set('confirmAddress', true)
        ->call('save')
        ->assertHasErrors($errorField);

    expect($user->fresh()->balance_sats)->toBe(1_000_000);
})->with([
    'typo in address' => ['0.001', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdx', 'address'],
    'testnet address' => ['0.001', 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kxpjzsx', 'address'],
    'more than balance' => ['0.02', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq', 'amount'],
]);

test('a user can join a plan after accepting the risks', function () {
    $user = User::factory()->withBalance(5_000_000)->create();
    $plan = Plan::factory()->create(['min_sats' => 1_000_000]);

    $component = Livewire::actingAs($user)->test('pages::invest')
        ->call('choose', $plan->id)
        ->set('amount', '0.02')
        ->call('invest')
        ->assertHasErrors('acceptRisk');

    $component->assertSee('Profit at the end')->assertSee('0.0006')->assertSee('0.0206');

    $component->set('acceptRisk', true)->call('invest')->assertHasNoErrors()->assertRedirect(route('investments'));

    expect($user->investments()->sole()->amount_sats)->toBe(2_000_000)
        ->and($user->fresh()->balance_sats)->toBe(3_000_000);
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
