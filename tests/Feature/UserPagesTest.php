<?php

use App\Enums\KycStatus;
use App\Enums\RequestStatus;
use App\Models\Announcement;
use App\Models\Investment;
use App\Models\KycSubmission;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('every user page opens and shows the demo notice', function (string $route) {
    $user = User::factory()->withBalance(1_000_000)->create();
    Investment::factory()->for($user)->create();

    $this->actingAs($user)->get(route($route))
        ->assertOk()
        ->assertSee('Demo platform, no real funds');
})->with(['dashboard', 'invest', 'investments', 'deposit', 'withdraw', 'transactions', 'kyc', 'referrals']);

test('guests and unverified users cannot open the wallet pages', function () {
    $this->get(route('withdraw'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('withdraw'))->assertRedirect(route('verification.notice'));
});

test('the dashboard shows published announcements only', function () {
    Announcement::factory()->create(['title' => 'Maintenance on Sunday']);
    Announcement::factory()->create(['title' => 'Hidden draft', 'is_published' => false]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('Maintenance on Sunday')
        ->assertDontSee('Hidden draft');
});

test('a user can record a deposit', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::deposit')
        ->set('amount', '0.05')
        ->set('reference', 'abc123')
        ->call('save')
        ->assertHasNoErrors();

    $deposit = $user->deposits()->sole();
    expect($deposit->amount_sats)->toBe(5_000_000)
        ->and($deposit->status)->toBe(RequestStatus::Pending)
        ->and($user->fresh()->balance_sats)->toBe(0);
});

test('deposit amounts must be valid BTC', function (string $amount) {
    Livewire::actingAs(User::factory()->create())->test('pages::deposit')
        ->set('amount', $amount)
        ->call('save')
        ->assertHasErrors('amount');
})->with(['', 'abc', '0.123456789', '-1', '0.0000001']);

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

    $component->set('acceptRisk', true)->call('invest')->assertHasNoErrors()->assertRedirect(route('investments'));

    expect($user->investments()->sole()->amount_sats)->toBe(2_000_000)
        ->and($user->fresh()->balance_sats)->toBe(3_000_000);
});

test('a user can send identity documents, which are stored privately', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::kyc')
        ->set('fullName', 'Ada Okafor')
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
