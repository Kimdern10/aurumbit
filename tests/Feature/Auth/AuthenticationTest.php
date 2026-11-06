<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_login_and_register_screens_use_the_site_styling(): void
    {
        foreach (['login' => 'Welcome back', 'register' => 'Get started'] as $routeName => $eyebrow) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee(__($eyebrow))
                ->assertSee(__('Secured with end-to-end encryption'))
                ->assertDontSee('<html lang="en" class="dark">', false)
                ->assertSee('data-prices-url="'.route('prices').'"', false)
                ->assertSee('data-price="btc"', false)
                ->assertSee(__('fixed-term plans.'))
                ->assertSee(__('Back to home'));
        }
    }

    public function test_each_logo_on_the_login_screen_has_its_own_gradient(): void
    {
        preg_match_all('/<linearGradient id="(hashvale-logo-[^"]+)"/', $this->get(route('login'))->getContent(), $matches);

        $this->assertCount(2, $matches[1]);
        $this->assertCount(2, array_unique($matches[1]));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('email');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
