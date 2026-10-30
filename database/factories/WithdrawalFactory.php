<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
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
            'amount_sats' => fake()->numberBetween(1, 10) * 100_000,
            'btc_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
        ];
    }
}
