<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
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
            'plan_id' => Plan::factory(),
            'amount_micros' => 1_000_000_000,
            'target_return_bps' => 300,
            'duration_days' => 30,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ];
    }
}
