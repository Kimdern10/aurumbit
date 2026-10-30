<?php

namespace Database\Seeders;

use App\Enums\PlanCategory;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Starter plans. Targets are indicative only; the admin can change everything.
     */
    public function run(): void
    {
        $plans = [
            ['slug' => 'hashrate-starter', 'name' => 'Hashrate Starter', 'category' => PlanCategory::Bitcoin, 'summary' => 'A short term linked to a share of Bitcoin mining hashrate. Good for learning how terms and settlement work.', 'min_sats' => 100_000, 'max_sats' => 5_000_000, 'duration_days' => 14, 'target_return_bps' => 80, 'risk_level' => 'high'],
            ['slug' => 'hashrate-core', 'name' => 'Hashrate Core', 'category' => PlanCategory::Bitcoin, 'summary' => 'A 60-day term following a pool of modern ASIC miners. Output depends on network difficulty and the BTC price.', 'min_sats' => 1_000_000, 'max_sats' => 50_000_000, 'duration_days' => 60, 'target_return_bps' => 350, 'risk_level' => 'high'],
            ['slug' => 'hashrate-reserve', 'name' => 'Hashrate Reserve', 'category' => PlanCategory::Bitcoin, 'summary' => 'A longer 180-day term. Your principal is locked for the whole period and may be affected by the next halving.', 'min_sats' => 5_000_000, 'max_sats' => null, 'duration_days' => 180, 'target_return_bps' => 900, 'risk_level' => 'very_high'],
            ['slug' => 'alluvial-gold', 'name' => 'Alluvial Gold', 'category' => PlanCategory::Gold, 'summary' => 'Linked to small-scale alluvial gold recovery. Funded in BTC; the gold equivalent is shown when you start.', 'min_sats' => 200_000, 'max_sats' => 10_000_000, 'duration_days' => 30, 'target_return_bps' => 150, 'risk_level' => 'medium'],
            ['slug' => 'hard-rock-gold', 'name' => 'Hard-Rock Gold', 'category' => PlanCategory::Gold, 'summary' => 'A 90-day term linked to hard-rock gold mining operations. Ore grades, permits and gold prices all affect results.', 'min_sats' => 1_000_000, 'max_sats' => 30_000_000, 'duration_days' => 90, 'target_return_bps' => 500, 'risk_level' => 'high'],
            ['slug' => 'gold-reserve', 'name' => 'Gold Reserve', 'category' => PlanCategory::Gold, 'summary' => 'A 180-day term for larger amounts, linked to a portfolio of gold mining projects.', 'min_sats' => 10_000_000, 'max_sats' => null, 'duration_days' => 180, 'target_return_bps' => 1000, 'risk_level' => 'very_high'],
            ['slug' => 'ether-stake', 'name' => 'Ether Stake', 'category' => PlanCategory::Ethereum, 'summary' => 'A 30-day term linked to Ethereum validator staking rewards. Funded in BTC.', 'min_sats' => 200_000, 'max_sats' => 20_000_000, 'duration_days' => 30, 'target_return_bps' => 120, 'risk_level' => 'high'],
            ['slug' => 'ether-stake-plus', 'name' => 'Ether Stake Plus', 'category' => PlanCategory::Ethereum, 'summary' => 'A 90-day Ethereum staking term for larger amounts.', 'min_sats' => 2_000_000, 'max_sats' => null, 'duration_days' => 90, 'target_return_bps' => 420, 'risk_level' => 'high'],
            ['slug' => 'solana-stake', 'name' => 'Solana Stake', 'category' => PlanCategory::Solana, 'summary' => 'A 45-day term linked to Solana staking rewards. Funded in BTC.', 'min_sats' => 200_000, 'max_sats' => 20_000_000, 'duration_days' => 45, 'target_return_bps' => 280, 'risk_level' => 'very_high'],
            ['slug' => 'top-10-index', 'name' => 'Top 10 Crypto Index', 'category' => PlanCategory::CryptoIndex, 'summary' => 'A 60-day term spread across the ten largest crypto assets by market value.', 'min_sats' => 500_000, 'max_sats' => 50_000_000, 'duration_days' => 60, 'target_return_bps' => 300, 'risk_level' => 'very_high'],
        ];

        foreach ($plans as $order => $plan) {
            Plan::query()->firstOrCreate(['slug' => $plan['slug']], [...$plan, 'sort_order' => $order]);
        }
    }
}
