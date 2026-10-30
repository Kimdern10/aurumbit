<?php

namespace App\Services;

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Enums\RequestStatus;
use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AccountActivity;
use App\Support\Btc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place balances change. Every change runs in a database transaction
 * with the user's row locked, and writes a ledger entry (the audit log).
 *
 * The site never holds private keys: the admin checks deposits on the
 * blockchain before crediting them and pays withdrawals from the company wallet.
 */
class WalletService
{
    public function __construct(private MarketPrices $prices) {}

    /**
     * Record a deposit the user says they sent. Nothing is credited until an admin approves it.
     */
    public function requestDeposit(User $user, int $amountSats, ?string $reference = null): Deposit
    {
        $minimum = Setting::integer('min_deposit_sats');

        if ($amountSats < $minimum) {
            throw new WalletException('The smallest deposit is '.Btc::format($minimum).' BTC.');
        }

        return $user->deposits()->create(['amount_sats' => $amountSats, 'reference' => $reference]);
    }

    public function approveDeposit(Deposit $deposit, User $admin): void
    {
        DB::transaction(function () use ($deposit, $admin): void {
            $deposit = $this->lockPendingDeposit($deposit->id);
            $user = $this->lockUser($deposit->user_id);

            $this->apply($user, $deposit->amount_sats, LedgerType::Deposit, 'Deposit #'.$deposit->id.' credited', $deposit, $admin);
            $this->markReviewed($deposit, RequestStatus::Approved, $admin);

            $user->notify(new AccountActivity('Deposit credited', Btc::format($deposit->amount_sats).' BTC has been added to your balance.', route('transactions'), 'success'));

            $this->payReferralBonus($user, $deposit, $admin);
        });
    }

    public function rejectDeposit(Deposit $deposit, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($deposit, $admin, $note): void {
            $locked = $this->lockPendingDeposit($deposit->id);
            $this->markReviewed($locked, RequestStatus::Rejected, $admin, $note);

            $locked->user->notify(new AccountActivity('Deposit not credited', 'Your deposit of '.Btc::format($locked->amount_sats).' BTC was rejected'.($note ? ': '.$note : '.'), route('deposit'), 'danger'));
        });
    }

    /**
     * Hold the amount from the balance straight away so it cannot be spent twice.
     */
    public function requestWithdrawal(User $user, int $amountSats, string $address): Withdrawal
    {
        $minimum = Setting::integer('min_withdrawal_sats');

        if ($amountSats < $minimum) {
            throw new WalletException('The smallest withdrawal is '.Btc::format($minimum).' BTC.');
        }

        if (Setting::enabled('withdrawal_requires_kyc') && ! $user->isKycVerified()) {
            throw new WalletException('Please verify your identity before withdrawing.');
        }

        return DB::transaction(function () use ($user, $amountSats, $address): Withdrawal {
            $locked = $this->lockUser($user->id);

            $withdrawal = $locked->withdrawals()->create(['amount_sats' => $amountSats, 'btc_address' => trim($address)]);

            $this->apply($locked, -$amountSats, LedgerType::Withdrawal, 'Withdrawal #'.$withdrawal->id.' requested', $withdrawal);

            $user->balance_sats = $locked->balance_sats;

            return $withdrawal;
        });
    }

    /**
     * Mark the withdrawal as paid, after the admin has sent the coins from the
     * company wallet, and record the blockchain transaction ID.
     */
    public function approveWithdrawal(Withdrawal $withdrawal, User $admin, string $payoutTxid, ?string $note = null): void
    {
        if (preg_match('/^[0-9a-fA-F]{64}$/', $payoutTxid) !== 1) {
            throw new WalletException('Enter the 64-character transaction ID of the payment.');
        }

        DB::transaction(function () use ($withdrawal, $admin, $payoutTxid, $note): void {
            $locked = $this->lockPendingWithdrawal($withdrawal->id);
            $locked->forceFill(['payout_txid' => strtolower($payoutTxid)]);
            $this->markReviewed($locked, RequestStatus::Approved, $admin, $note);

            $locked->user->notify(new AccountActivity('Withdrawal sent', Btc::format($locked->amount_sats).' BTC has been sent to your wallet.', route('withdraw'), 'success'));
        });
    }

    /**
     * Refuse the withdrawal and return the held amount to the balance.
     */
    public function rejectWithdrawal(Withdrawal $withdrawal, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $note): void {
            $withdrawal = $this->lockPendingWithdrawal($withdrawal->id);
            $user = $this->lockUser($withdrawal->user_id);

            $this->apply($user, $withdrawal->amount_sats, LedgerType::WithdrawalRefund, 'Withdrawal #'.$withdrawal->id.' rejected, amount returned', $withdrawal, $admin);
            $this->markReviewed($withdrawal, RequestStatus::Rejected, $admin, $note);

            $user->notify(new AccountActivity('Withdrawal rejected', Btc::format($withdrawal->amount_sats).' BTC is back in your balance'.($note ? '. Reason: '.$note : '.'), route('withdraw'), 'danger'));
        });
    }

    /**
     * Move money from the balance into a plan.
     */
    public function invest(User $user, Plan $plan, int $amountSats): Investment
    {
        if (! $plan->is_active) {
            throw new WalletException('This plan is not open for new investments.');
        }

        if ($amountSats < $plan->min_sats || ($plan->max_sats !== null && $amountSats > $plan->max_sats)) {
            throw new WalletException('Enter an amount within the plan limits.');
        }

        $goldMg = $plan->category->value === 'gold' ? $this->prices->goldMilligrams($amountSats) : null;

        return DB::transaction(function () use ($user, $plan, $amountSats, $goldMg): Investment {
            $locked = $this->lockUser($user->id);
            $now = now();

            $investment = $locked->investments()->create([
                'plan_id' => $plan->id,
                'amount_sats' => $amountSats,
                'target_return_bps' => $plan->target_return_bps,
                'duration_days' => $plan->duration_days,
                'gold_mg' => $goldMg,
                'starts_at' => $now,
                'ends_at' => $now->addDays($plan->duration_days),
            ]);

            $this->apply($locked, -$amountSats, LedgerType::Investment, 'Invested in '.$plan->name, $investment);

            $user->balance_sats = $locked->balance_sats;

            return $investment;
        });
    }

    /**
     * Update the earnings of an active investment for the days passed.
     * Safe to run many times: it sets the figure rather than adding to it.
     */
    public function accrue(Investment $investment): void
    {
        DB::transaction(function () use ($investment): void {
            $locked = Investment::query()->whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvestmentStatus::Active) {
                $locked->earned_sats = max($locked->earned_sats, $locked->earningsAt());
                $locked->save();
            }
        });
    }

    /**
     * Close a finished investment: return the principal and pay the earnings.
     */
    public function settle(Investment $investment, ?User $admin = null): void
    {
        DB::transaction(function () use ($investment, $admin): void {
            $locked = Investment::query()->with('plan')->whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvestmentStatus::Active) {
                throw new WalletException('This investment is already closed.');
            }

            if ($admin === null && $locked->ends_at->isFuture()) {
                throw new WalletException('This investment has not finished its term yet.');
            }

            $user = $this->lockUser($locked->user_id);
            $earned = max($locked->earned_sats, $locked->earningsAt($locked->ends_at->isPast() ? $locked->ends_at : now()));

            $this->apply($user, $locked->amount_sats, LedgerType::InvestmentReturn, $locked->plan->name.' principal returned', $locked, $admin);

            if ($earned > 0) {
                $this->apply($user, $earned, LedgerType::Earning, $locked->plan->name.' earnings', $locked, $admin);
            }

            $locked->forceFill(['earned_sats' => $earned, 'status' => InvestmentStatus::Completed, 'settled_at' => now()])->save();

            $user->notify(new AccountActivity('Plan completed', $locked->plan->name.' finished. '.Btc::format($locked->amount_sats + $earned).' BTC (including '.Btc::format($earned).' BTC profit) is in your balance.', route('investments'), 'success'));
        });
    }

    /**
     * Stop an investment early and return only the principal.
     */
    public function cancel(Investment $investment, User $admin): void
    {
        DB::transaction(function () use ($investment, $admin): void {
            $locked = Investment::query()->with('plan')->whereKey($investment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InvestmentStatus::Active) {
                throw new WalletException('This investment is already closed.');
            }

            $user = $this->lockUser($locked->user_id);

            $this->apply($user, $locked->amount_sats, LedgerType::InvestmentReturn, $locked->plan->name.' cancelled, principal returned', $locked, $admin);

            $locked->forceFill(['earned_sats' => 0, 'status' => InvestmentStatus::Cancelled, 'settled_at' => now()])->save();
        });
    }

    /**
     * A manual correction by an admin, positive or negative, with a reason.
     */
    public function adjust(User $user, int $amountSats, string $reason, User $admin): void
    {
        if ($amountSats === 0) {
            throw new WalletException('Enter an amount other than zero.');
        }

        DB::transaction(function () use ($user, $amountSats, $reason, $admin): void {
            $this->apply($this->lockUser($user->id), $amountSats, LedgerType::Adjustment, $reason, null, $admin);
        });
    }

    /**
     * Pay the referrer a share of the first approved deposit of someone they invited.
     */
    private function payReferralBonus(User $user, Deposit $deposit, User $admin): void
    {
        $bonus = intdiv($deposit->amount_sats * Setting::integer('referral_bonus_bps'), 10000);

        if ($user->referred_by_id === null || $bonus <= 0) {
            return;
        }

        $isFirst = ! Deposit::query()
            ->where('user_id', $user->id)
            ->where('status', RequestStatus::Approved)
            ->whereKeyNot($deposit->id)
            ->exists();

        if (! $isFirst) {
            return;
        }

        $referrer = $this->lockUser($user->referred_by_id);

        $this->apply($referrer, $bonus, LedgerType::ReferralBonus, 'Referral bonus for '.$user->name, $deposit, $admin);

        $referrer->notify(new AccountActivity('Referral bonus', 'You earned '.Btc::format($bonus).' BTC because someone you invited made their first deposit.', route('referrals'), 'success'));
    }

    /**
     * Change a locked user's balance and write the audit entry.
     */
    private function apply(User $user, int $amountSats, LedgerType $type, string $description, ?Model $subject = null, ?User $actor = null): LedgerEntry
    {
        $newBalance = $user->balance_sats + $amountSats;

        if ($newBalance < 0) {
            throw new WalletException('Your available balance is too low for this.');
        }

        $user->forceFill(['balance_sats' => $newBalance])->save();

        return LedgerEntry::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'amount_sats' => $amountSats,
            'balance_after_sats' => $newBalance,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'actor_id' => $actor?->id,
        ]);
    }

    private function lockUser(int $userId): User
    {
        return User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Lock a deposit and make sure nobody has reviewed it yet.
     */
    private function lockPendingDeposit(int $id): Deposit
    {
        $deposit = Deposit::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        $this->ensurePending($deposit);

        return $deposit;
    }

    /**
     * Lock a withdrawal and make sure nobody has reviewed it yet.
     */
    private function lockPendingWithdrawal(int $id): Withdrawal
    {
        $withdrawal = Withdrawal::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        $this->ensurePending($withdrawal);

        return $withdrawal;
    }

    private function ensurePending(Deposit|Withdrawal $request): void
    {
        if ($request->status !== RequestStatus::Pending) {
            throw new WalletException('This request has already been reviewed.');
        }
    }

    private function markReviewed(Deposit|Withdrawal $request, RequestStatus $status, User $admin, ?string $note = null): void
    {
        $request->forceFill([
            'status' => $status,
            'admin_note' => $note,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();
    }
}
