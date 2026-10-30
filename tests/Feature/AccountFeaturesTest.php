<?php

use App\Models\Deposit;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\WalletService;
use Livewire\Livewire;

test('users are notified when a deposit is credited and can mark notifications read', function () {
    $user = User::factory()->create();
    $deposit = Deposit::factory()->for($user)->create(['amount_sats' => 2_500_000]);

    app(WalletService::class)->approveDeposit($deposit, User::factory()->admin()->create());

    $notification = $user->notifications()->sole();
    expect($notification->data['title'])->toBe('Deposit credited')
        ->and($notification->data['body'])->toContain('0.025 BTC');

    $this->actingAs($user)->get(route('dashboard'))->assertSee(route('notifications'));

    Livewire::actingAs($user)->test('pages::notifications')
        ->assertSee('Deposit credited')
        ->call('markAllRead');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('rejections tell the user the reason', function () {
    $user = User::factory()->create();
    $deposit = Deposit::factory()->for($user)->create();

    app(WalletService::class)->rejectDeposit($deposit, User::factory()->admin()->create(), 'Transaction not found');

    expect($user->notifications()->sole()->data['body'])->toContain('Transaction not found');
});

test('a user opens a support ticket and staff reply', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($user)->test('pages::support')
        ->set('subject', 'Deposit not showing')
        ->set('message', 'I sent 0.01 BTC an hour ago.')
        ->call('save')
        ->assertHasNoErrors();

    $ticket = $user->supportTickets()->sole();
    expect($ticket->status)->toBe('open')
        ->and($ticket->messages()->sole()->from_staff)->toBeFalse();

    Livewire::actingAs($admin)->test('pages::admin.ticket', ['ticket' => $ticket])
        ->set('message', 'It is confirmed now and credited.')
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe('answered')
        ->and($ticket->fresh()->messages->last()->from_staff)->toBeTrue()
        ->and($user->notifications()->sole()->data['title'])->toBe('Support replied');

    Livewire::actingAs($user)->test('pages::support-ticket', ['ticket' => $ticket->fresh()])
        ->assertSee('It is confirmed now and credited.')
        ->set('message', 'Thank you!')
        ->call('send');

    expect($ticket->fresh()->status)->toBe('open');
});

test('users cannot read other people\'s tickets or reply to closed ones', function () {
    $ticket = SupportTicket::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('support.show', $ticket))->assertNotFound();

    $ticket->forceFill(['status' => 'closed'])->save();

    Livewire::actingAs($ticket->user)->test('pages::support-ticket', ['ticket' => $ticket])
        ->set('message', 'Hello again')
        ->call('send')
        ->assertForbidden();
});

test('users can download their statement as CSV', function () {
    $user = User::factory()->create();
    app(WalletService::class)->approveDeposit(Deposit::factory()->for($user)->create(['amount_sats' => 1_250_000]), User::factory()->admin()->create());
    User::factory()->create()->ledgerEntries()->create(['type' => 'adjustment', 'amount_sats' => 999, 'balance_after_sats' => 999, 'description' => 'Someone else']);

    $response = $this->actingAs($user)->get(route('transactions.export'))->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Date,Type,Details')
        ->toContain('Deposit,"Deposit #')
        ->toContain('0.01250000')
        ->not->toContain('Someone else');
});

test('the plan form shows admins what a customer would earn', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test('pages::admin.plans')
        ->call('create')
        ->set('durationDays', 30)
        ->set('targetPercent', '3')
        ->assertSee('Customer preview for 0.1 BTC')
        ->assertSee('+0.003 BTC profit')
        ->assertSee('0.103 BTC paid back after 30 days');

    expect(Plan::count())->toBe(0);
});
