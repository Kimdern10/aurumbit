<?php

namespace Database\Seeders;

use App\Enums\PlanCategory;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Starter plans. The admin can change or remove every one of them.
     * Limits are in micro-USDT: 1 USDT = 1_000_000.
     */
    public function run(): void
    {
        $usdt = 1_000_000;

        $plans = [
            ['slug' => 'bitcoin-starter', 'name' => 'Bitcoin Starter', 'category' => PlanCategory::Bitcoin, 'summary' => 'A short 14-day plan with a fixed rate. Paid in USDT at the end of the term.', 'min_micros' => 50 * $usdt, 'max_micros' => 5_000 * $usdt, 'duration_days' => 14, 'target_return_bps' => 80],
            ['slug' => 'bitcoin-plus', 'name' => 'Bitcoin Plus', 'category' => PlanCategory::Bitcoin, 'summary' => 'A 60-day plan with a fixed rate. Paid in USDT at the end of the term.', 'min_micros' => 500 * $usdt, 'max_micros' => 50_000 * $usdt, 'duration_days' => 60, 'target_return_bps' => 350],
            ['slug' => 'bitcoin-premium', 'name' => 'Bitcoin Premium', 'category' => PlanCategory::Bitcoin, 'summary' => 'A 180-day plan for larger amounts with a higher fixed rate.', 'min_micros' => 5_000 * $usdt, 'max_micros' => null, 'duration_days' => 180, 'target_return_bps' => 900],
            ['slug' => 'gold-starter', 'name' => 'Gold Starter', 'category' => PlanCategory::Gold, 'summary' => 'A 30-day plan. See the gold value of your plan at today\'s price when you start.', 'min_micros' => 100 * $usdt, 'max_micros' => 10_000 * $usdt, 'duration_days' => 30, 'target_return_bps' => 150],
            ['slug' => 'gold-plus', 'name' => 'Gold Plus', 'category' => PlanCategory::Gold, 'summary' => 'A 90-day gold plan with a fixed rate.', 'min_micros' => 1_000 * $usdt, 'max_micros' => 30_000 * $usdt, 'duration_days' => 90, 'target_return_bps' => 500],
            ['slug' => 'gold-premium', 'name' => 'Gold Premium', 'category' => PlanCategory::Gold, 'summary' => 'A 180-day gold plan for larger amounts.', 'min_micros' => 10_000 * $usdt, 'max_micros' => null, 'duration_days' => 180, 'target_return_bps' => 1000],
            ['slug' => 'ethereum-starter', 'name' => 'Ethereum Starter', 'category' => PlanCategory::Ethereum, 'summary' => 'A 30-day plan with a fixed rate. Funded and paid in USDT.', 'min_micros' => 100 * $usdt, 'max_micros' => 20_000 * $usdt, 'duration_days' => 30, 'target_return_bps' => 120],
            ['slug' => 'ethereum-plus', 'name' => 'Ethereum Plus', 'category' => PlanCategory::Ethereum, 'summary' => 'A 90-day plan for larger amounts. Funded and paid in USDT.', 'min_micros' => 2_000 * $usdt, 'max_micros' => null, 'duration_days' => 90, 'target_return_bps' => 420],
            ['slug' => 'solana-starter', 'name' => 'Solana Starter', 'category' => PlanCategory::Solana, 'summary' => 'A 45-day plan with a fixed rate. Funded and paid in USDT.', 'min_micros' => 100 * $usdt, 'max_micros' => 20_000 * $usdt, 'duration_days' => 45, 'target_return_bps' => 280],
            ['slug' => 'crypto-mix', 'name' => 'Crypto Mix', 'category' => PlanCategory::CryptoIndex, 'summary' => 'A 60-day plan with a fixed rate. Funded and paid in USDT.', 'min_micros' => 250 * $usdt, 'max_micros' => 50_000 * $usdt, 'duration_days' => 60, 'target_return_bps' => 300],
            ['slug' => 'usdt-starter', 'name' => 'USDT Starter', 'category' => PlanCategory::Usdt, 'summary' => 'A 30-day stable plan in USDT, with no price swings on your capital.', 'min_micros' => 50 * $usdt, 'max_micros' => 10_000 * $usdt, 'duration_days' => 30, 'target_return_bps' => 100],
            ['slug' => 'usdt-plus', 'name' => 'USDT Plus', 'category' => PlanCategory::Usdt, 'summary' => 'A 90-day stable plan in USDT for larger amounts.', 'min_micros' => 1_000 * $usdt, 'max_micros' => null, 'duration_days' => 90, 'target_return_bps' => 400],
        ];

        foreach ($plans as $order => $plan) {
            Plan::query()->firstOrCreate(['slug' => $plan['slug']], [...$plan, 'sort_order' => $order]);
        }
    }
}
