<?php

namespace Database\Factories;

use App\Enums\WithdrawalNetwork;
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
            'amount_micros' => fake()->numberBetween(10, 500) * 1_000_000,
            'network' => WithdrawalNetwork::Trc20,
            'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
        ];
    }
}
