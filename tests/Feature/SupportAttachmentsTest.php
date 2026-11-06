<?php

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AccountActivity;
use App\Notifications\AccountActivityEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a user can attach a screenshot to a new ticket, stored privately', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::support')
        ->set('subject', 'Deposit missing')
        ->set('message', 'I sent USDT an hour ago and it is not showing.')
        ->set('attachment', UploadedFile::fake()->image('wallet-screen.png'))
        ->call('save')
        ->assertHasNoErrors();

    $message = $user->supportTickets()->sole()->messages()->sole();
    expect($message->attachment_name)->toBe('wallet-screen.png')
        ->and($message->attachment_path)->toStartWith('support/'.$user->id.'/');
    Storage::disk('local')->assertExists($message->attachment_path);

    $this->actingAs($user)->get(route('support.show', $message->ticket))->assertSee('wallet-screen.png');
    $this->actingAs($user)->get($message->attachmentUrl())->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('only the ticket owner and admins can open an attachment', function () {
    Storage::fake('local');
    $ticket = SupportTicket::factory()->create();
    $message = $ticket->reply($ticket->user, 'See the file', UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'));

    $this->actingAs(User::factory()->create())->get(route('support.attachment', $message))->assertNotFound();
    $this->actingAs(User::factory()->support()->create())->get($message->attachmentUrl(forAdmin: true))->assertOk();
});

test('attachments must be an image or PDF of at most 5 MB', function (UploadedFile $file) {
    Storage::fake('local');
    $ticket = SupportTicket::factory()->create();

    Livewire::actingAs($ticket->user)->test('pages::support-ticket', ['ticket' => $ticket])
        ->set('message', 'Here it is')
        ->set('attachment', $file)
        ->call('send')
        ->assertHasErrors('attachment');
})->with([
    'script' => fn () => UploadedFile::fake()->create('virus.exe', 10),
    'html' => fn () => UploadedFile::fake()->create('page.html', 10, 'text/html'),
    'too big' => fn () => UploadedFile::fake()->image('huge.png')->size(6000),
]);

test('admin replies can carry a file and the user is emailed the reply', function () {
    Storage::fake('local');
    Notification::fake();
    $ticket = SupportTicket::factory()->create();

    Livewire::actingAs(User::factory()->support()->create())->test('pages::admin.ticket', ['ticket' => $ticket])
        ->set('message', 'Your deposit was credited, see the receipt.')
        ->set('attachment', UploadedFile::fake()->image('receipt.jpg'))
        ->call('send')
        ->assertHasNoErrors();

    expect($ticket->messages()->latest('id')->first()->attachment_name)->toBe('receipt.jpg');

    $sent = collect(Notification::sent($ticket->user, AccountActivity::class))->sole();
    expect($sent->title)->toBe('Support replied')
        ->and($sent->body)->toContain('Your deposit was credited, see the receipt.');
});

test('the support reply reaches the user by email', function () {
    Notification::fake();
    $ticket = SupportTicket::factory()->create();

    event(new NotificationSent($ticket->user, new AccountActivity('Support replied', 'We replied'), 'database'));

    expect(collect(Notification::sent($ticket->user, AccountActivityEmail::class))->pluck('title')->all())->toBe(['Support replied']);
});

test('deleting an account removes its support files', function () {
    Storage::fake('local');
    $ticket = SupportTicket::factory()->create();
    $message = $ticket->reply($ticket->user, 'File', UploadedFile::fake()->image('a.png'));

    $ticket->user->delete();

    Storage::disk('local')->assertMissing($message->attachment_path);
});
