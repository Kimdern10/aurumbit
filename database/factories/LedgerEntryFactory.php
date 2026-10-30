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
            'amount_sats' => 100_000,
            'balance_after_sats' => 100_000,
            'description' => 'Test entry',
        ];
    }
}
