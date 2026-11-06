<?php

namespace Database\Factories;

use App\Enums\LedgerType;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
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
            'type' => LedgerType::Adjustment,
            'amount_micros' => 100_000_000,
            'balance_after_micros' => 100_000_000,
            'description' => 'Test entry',
        ];
    }
}
