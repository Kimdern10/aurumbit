<?php

use App\Enums\AdminRole;
use App\Models\AdminSignIn;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\DatabaseBackup;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('support staff can open the review pages but not the owner-only ones', function () {
    $support = User::factory()->support()->create();

    foreach (['admin.dashboard', 'admin.users', 'admin.deposits', 'admin.withdrawals', 'admin.kyc', 'admin.tickets', 'admin.investments'] as $route) {
        $this->actingAs($support)->get(route($route))->assertOk();
    }

    foreach (['admin.plans', 'admin.wallets', 'admin.settings', 'admin.ledger', 'admin.sign-ins', 'admin.backups'] as $route) {
        $this->actingAs($support)->get(route($route))->assertForbidden();
    }

    $this->actingAs($support)->get(route('admin.dashboard'))
        ->assertDontSee(route('admin.settings'))
        ->assertSee('Admin panel · Support');
});

test('support staff cannot move money, even by calling the actions directly', function () {
    $support = User::factory()->support()->create();
    $customer = User::factory()->withBalance(100_000_000)->create();
    $deposit = Deposit::factory()->for($customer)->create(['coin_amount' => '50']);
    $withdrawal = Withdrawal::factory()->for($customer)->create();
    $investment = Investment::factory()->for($customer)->create();

    Livewire::actingAs($support)->test('pages::admin.deposits')
        ->assertDontSee('startApprove', false)
        ->call('startApprove', $deposit->id)
        ->assertForbidden();

    Livewire::actingAs($support)->test('pages::admin.withdrawals')
        ->call('review', $withdrawal->id, 'approve')
        ->assertForbidden();

    Livewire::actingAs($support)->test('pages::admin.investments')
        ->call('cancel', $investment->id)
        ->assertForbidden();

    Livewire::actingAs($support)->test('pages::admin.user', ['user' => $customer])
        ->assertDontSee('Adjust balance')
        ->set('adjustAmount', '500')
        ->set('adjustReason', 'Free money please')
        ->call('adjust')
        ->assertForbidden();

    expect($customer->fresh()->balance_micros)->toBe(100_000_000)
        ->and($deposit->fresh()->status->value)->toBe('pending');
});

test('owners keep full access', function () {
    $owner = User::factory()->admin()->create();
    Deposit::factory()->create();

    expect($owner->isOwner())->toBeTrue();
    $this->actingAs($owner)->get(route('admin.settings'))->assertOk();
    Livewire::actingAs($owner)->test('pages::admin.deposits')->assertSee('startApprove', false);
});

test('the create-admin command can make a support admin', function () {
    $this->artisan('app:create-admin', ['--name' => 'Helper', '--email' => 'help@example.com', '--password' => 'secret-password', '--role' => 'support'])
        ->assertSuccessful();

    expect(User::query()->where('email', 'help@example.com')->sole()->admin_role)->toBe(AdminRole::Support);

    $this->artisan('app:create-admin', ['--email' => 'help@example.com', '--password' => 'secret-password', '--role' => 'boss'])
        ->assertFailed();
});

test('every admin sign-in is logged, customer sign-ins are not', function () {
    $admin = User::factory()->support()->create();
    $customer = User::factory()->create();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36')
        ->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);
    auth()->logout();
    $this->post(route('login.store'), ['email' => $customer->email, 'password' => 'password']);

    $signIn = AdminSignIn::query()->sole();
    expect($signIn->user_id)->toBe($admin->id)
        ->and($signIn->ip_address)->toBe('127.0.0.1')
        ->and($signIn->device())->toBe('Chrome on Windows');

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.sign-ins'))
        ->assertOk()
        ->assertSee($admin->name)
        ->assertSee('Chrome on Windows');
});

test('owners can make and download a backup from the admin', function () {
    Storage::fake('local');
    $owner = User::factory()->admin()->create();

    $component = Livewire::actingAs($owner)->test('pages::admin.backups')
        ->assertSee('No backups yet')
        ->call('backUpNow');

    $backup = DatabaseBackup::list()[0];
    $component->assertSee($backup['name'])
        ->call('download', $backup['name'])
        ->assertFileDownloaded($backup['name']);

    Livewire::actingAs($owner)->test('pages::admin.backups')
        ->call('download', '../../.env')
        ->assertNotFound();
});

test('owners can give a user a role, change it and take it away', function () {
    $owner = User::factory()->admin()->create();
    $user = User::factory()->create();

    Livewire::actingAs($owner)->test('pages::admin.user', ['user' => $user])
        ->assertSet('role', 'customer')
        ->set('role', 'support')
        ->call('saveRole')
        ->assertHasNoErrors();

    expect($user->fresh()->is_admin)->toBeTrue()
        ->and($user->fresh()->admin_role)->toBe(AdminRole::Support);

    Livewire::actingAs($owner)->test('pages::admin.user', ['user' => $user->fresh()])
        ->assertSet('role', 'support')
        ->set('role', 'customer')
        ->call('saveRole');

    expect($user->fresh()->is_admin)->toBeFalse()
        ->and($user->fresh()->admin_role)->toBeNull();

    Livewire::actingAs($owner)->test('pages::admin.user', ['user' => $user])
        ->set('role', 'god')
        ->call('saveRole')
        ->assertHasErrors('role');
});

test('support staff cannot change roles and nobody can change their own', function () {
    $support = User::factory()->support()->create();
    $owner = User::factory()->admin()->create();

    Livewire::actingAs($support)->test('pages::admin.user', ['user' => User::factory()->create()])
        ->assertDontSee('data-role-card', false)
        ->set('role', 'owner')
        ->call('saveRole')
        ->assertForbidden();

    Livewire::actingAs($owner)->test('pages::admin.user', ['user' => $owner])
        ->set('role', 'customer')
        ->call('saveRole')
        ->assertStatus(422);

    expect($support->fresh()->isOwner())->toBeFalse()
        ->and($owner->fresh()->isOwner())->toBeTrue();
});

test('the backups list is paginated', function () {
    Storage::fake('local');
    foreach (range(1, 12) as $i) {
        Storage::disk('local')->put(sprintf('backups/backup-2026-01-%02d-020000.zip', $i), 'x');
    }

    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.backups')
        ->assertSee('backup-2026-01-12')
        ->assertDontSee('backup-2026-01-02-')
        ->call('gotoPage', 2)
        ->assertSee('backup-2026-01-02-');
});
