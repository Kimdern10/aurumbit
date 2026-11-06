<?php

namespace Database\Factories;

use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'deposit_wallet_id' => DepositWallet::factory(),
            'coin' => 'USDT',
            'network' => 'TRC20',
            'coin_amount' => (string) fake()->numberBetween(20, 2000),
            'reference' => fake()->optional()->sha256(),
        ];
    }

    /**
     * An approved deposit with the USDT it credited.
     */
    public function approved(int $creditedMicros): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'approved', 'credited_micros' => $creditedMicros]);
    }
}
