<?php

namespace Database\Factories;

use App\Models\DepositWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositWallet>
 */
class DepositWalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coin' => 'USDT',
            'network' => 'TRC20',
            'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'explorer_tx_url' => 'https://tronscan.org/#/transaction/',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function bitcoin(): static
    {
        return $this->state(fn (array $attributes) => [
            'coin' => 'BTC',
            'network' => 'Bitcoin',
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'explorer_tx_url' => 'https://mempool.space/tx/',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
