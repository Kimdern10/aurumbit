<?php

namespace App\Support;

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Enums\RequestStatus;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Withdrawal;

/**
 * Splits the money on the platform into real money (deposits that arrived,
 * withdrawals that were paid) and money the platform created (admin
 * adjustments, plan earnings, referral bonuses), all in micro-USDT.
 */
class MoneySummary
{
    /**
     * @return array{
     *     real_deposits: int,
     *     paid_out: int,
     *     on_hold: int,
     *     real_money_held: int,
     *     adjustments: int,
     *     earnings: int,
     *     bonuses: int,
     *     owed_to_customers: int,
     * }
     */
    public static function totals(): array
    {
        return once(function (): array {
            $realDeposits = (int) Deposit::query()->where('status', RequestStatus::Approved)->sum('credited_micros');
            $paidOut = (int) Withdrawal::query()->where('status', RequestStatus::Approved)->sum('amount_micros');
            $ledger = fn (LedgerType $type): int => (int) LedgerEntry::query()->where('type', $type)->sum('amount_micros');

            return [
                'real_deposits' => $realDeposits,
                'paid_out' => $paidOut,
                'on_hold' => (int) Withdrawal::query()->where('status', RequestStatus::Pending)->sum('amount_micros'),
                'real_money_held' => $realDeposits - $paidOut,
                'adjustments' => $ledger(LedgerType::Adjustment),
                'earnings' => $ledger(LedgerType::Earning),
                'bonuses' => $ledger(LedgerType::ReferralBonus),
                'owed_to_customers' => (int) User::query()->where('is_admin', false)->sum('balance_micros')
                    + (int) Investment::query()->where('status', InvestmentStatus::Active)->sum('amount_micros')
                    + (int) Withdrawal::query()->where('status', RequestStatus::Pending)->sum('amount_micros'),
            ];
        });
    }
}
