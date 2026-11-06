<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeAndMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_have_a_light_and_dark_toggle(): void
    {
        foreach (['home', 'login', 'register'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('data-theme-toggle', false)
                ->assertSee('$flux.dark = ! $flux.dark', false);
        }
    }

    public function test_dashboard_has_a_toggle_an_appearance_switch_and_a_sticky_menu_bar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-appearance-switch', false)
            ->assertSee('sticky top-0 z-10', false)
            ->assertSee(__('Open menu'));
    }

    public function test_dashboard_does_not_force_dark_mode(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertDontSee('<html lang="en" class="dark">', false);
    }

    public function test_admin_dashboard_has_the_menu_bar_and_toggle(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee(__('Open menu'));
    }
}
