<?php

namespace App\Enums;

/**
 * Every reason a wallet balance can change.
 */
enum LedgerType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case WithdrawalRefund = 'withdrawal_refund';
    case Investment = 'investment';
    case InvestmentReturn = 'investment_return';
    case Earning = 'earning';
    case ReferralBonus = 'referral_bonus';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::WithdrawalRefund => 'Withdrawal returned',
            self::Investment => 'Investment',
            self::InvestmentReturn => 'Principal returned',
            self::Earning => 'Earnings',
            self::ReferralBonus => 'Referral bonus',
            self::Adjustment => 'Balance adjustment',
        };
    }
}
