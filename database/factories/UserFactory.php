<?php

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['is_admin' => true, 'admin_role' => AdminRole::Owner]);
    }

    /**
     * An admin who can help users but cannot move money or change settings.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => ['is_admin' => true, 'admin_role' => AdminRole::Support]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['is_suspended' => true]);
    }

    /**
     * Start the user with a (simulated) balance in micro-USDT.
     */
    public function withBalance(int $micros): static
    {
        return $this->state(fn (array $attributes) => ['balance_micros' => $micros]);
    }
}
