<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Support\EmailVerificationCode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
    }

    public function test_unverified_users_cannot_reach_the_dashboard_or_admin_area(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_new_users_are_emailed_a_six_digit_code_instead_of_a_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        event(new Registered($user));

        Notification::assertNotSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return preg_match('/^\d{6}$/', $notification->code) === 1
                && str_contains($mail->subject, $notification->code)
                && ! str_contains(implode(' ', $mail->introLines), 'http');
        });
    }

    public function test_the_emailed_code_verifies_the_email(): void
    {
        Notification::fake();
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $code = $this->sendCode($user);

        Livewire::actingAs($user)->test('pages::auth.verify-email-code')
            ->set('code', substr($code, 0, 3).' '.substr($code, 3))
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_tries_use_up_the_code(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $code = $this->sendCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $component = Livewire::actingAs($user)->test('pages::auth.verify-email-code');

        foreach (range(1, 5) as $try) {
            $component->set('code', $wrong)->call('verify')->assertHasErrors('code');
        }

        $component->set('code', $code)->call('verify')->assertHasErrors('code')->assertSee('Send a new code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_codes_expire(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $code = $this->sendCode($user);

        $this->travel(EmailVerificationCode::MINUTES_VALID + 1)->minutes();

        Livewire::actingAs($user)->test('pages::auth.verify-email-code')
            ->set('code', $code)
            ->call('verify')
            ->assertHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_verification_page_sends_a_code_when_there_is_none_and_new_codes_are_limited_to_one_a_minute(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $component = Livewire::actingAs($user)->test('pages::auth.verify-email-code')
            ->assertSee('A new code is on its way');

        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);

        $component->call('resend')->assertHasErrors('code');
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 1);

        $this->travel(61)->seconds();
        $component->call('resend')->assertHasNoErrors();
        Notification::assertSentToTimes($user, VerifyEmailCode::class, 2);
    }

    /**
     * Send a code and read it back from the faked notification.
     */
    private function sendCode(User $user): string
    {
        $user->sendEmailVerificationNotification();
        $code = '';

        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    public function test_unverified_users_are_redirected_to_the_email_verification_prompt(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('appearance.edit'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertNotDispatched(Verified::class);
    }
}
