<?php

use App\Models\User;
use App\Notifications\AccountActivityEmail;
use App\Notifications\VerifyEmailCode;
use App\Notifications\WithdrawalCodeNotification;
use Illuminate\Auth\Notifications\ResetPassword;

test('every email shows the site logo and name, never the Laravel defaults', function (string $email) {
    $user = User::factory()->make();
    $mail = match ($email) {
        'verification code' => (new VerifyEmailCode('482913', 15))->toMail($user),
        'withdrawal code' => (new WithdrawalCodeNotification('482913', 10))->toMail($user),
        'password reset' => (new ResetPassword('token'))->toMail($user),
        'account activity' => (new AccountActivityEmail('Deposit credited', 'Done.', null, 'success'))->toMail($user),
    };
    $html = (string) $mail->render();

    expect($html)->toContain(asset('icon-192.png'))
        ->toContain('Hashvale')
        ->toContain('The Hashvale team')
        ->not->toContain('laravel.com')
        ->not->toContain('Regards,');
})->with(['verification code', 'withdrawal code', 'password reset', 'account activity']);

test('codes are shown in a large code box', function () {
    $html = (string) (new VerifyEmailCode('482913', 15))->toMail(User::factory()->make())->render();

    expect($html)->toContain('class="code-box"')->toContain('482 913');
});

test('the password reset email links to the reset page for that user', function () {
    $user = User::factory()->make(['email' => 'ada@example.com']);
    $mail = (new ResetPassword('secret-token'))->toMail($user);

    expect($mail->subject)->toBe('Reset your Hashvale password')
        ->and($mail->actionUrl)->toBe(route('password.reset', ['token' => 'secret-token', 'email' => 'ada@example.com']));
});
