<?php

namespace App\Services;

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Enums\RequestStatus;
use App\Enums\WithdrawalNetwork;
use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\AccountActivity;
use App\Rules\UsdtAddress;
use App\Support\Btc;
use App\Support\Usdt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place balances change. Every change runs in a database transaction
 * with the user's row locked, and writes a ledger entry (the audit log).
 *
 * Balances are kept in USDT (as micros). Deposits can arrive in any coin the
 * admin accepts and are credited at their USDT value; withdrawals are paid in USDT.
 *
 * The site never holds private keys: the admin checks deposits on the
 * blockchain before crediting them and pays withdrawals from the company wallet.
 */
class WalletService
{
    /**
     * Withdrawals are paused for this long after a password change, in case
     * someone else changed it.
     */
    public const int PASSWORD_CHANGE_HOLD_HOURS = 24;

    public function __construct(private MarketPrices $prices) {}

    /**
     * Record a deposit the user says they sent to one of our wallets. Nothing is
     * credited until an admin checks it and approves it.
     */
    public function requestDeposit(User $user, DepositWallet $wallet, string $coinAmount, ?string $reference = null): Deposit
    {
        if (! $wallet->is_active) {
            throw new WalletException('This wallet is not accepting deposits.');
        }

        $coinAmount = trim($coinAmount);

        if (Btc::toSats($coinAmount) === null || Btc::toSats($coinAmount) === 0) {
            throw new WalletException('Enter the amount you sent, with up to 8 decimal places.');
        }

        $minimum = Setting::integer('min_deposit_micros');
        $priceCents = DepositWallet::priceCentsFor($wallet->coin, $this->prices);

        if ($priceCents !== null && Usdt::fromCoin($coinAmount, $priceCents) < $minimum) {
            throw new WalletException('The smallest deposit is '.Usdt::format($minimum).' USDT worth of '.$wallet->coin.'.');
        }

        $deposit = $user->deposits()->create([
            'deposit_wallet_id' => $wallet->id,
            'coin' => $wallet->coin,
            'network' => $wallet->network,
            'coin_amount' => $coinAmount,
            'reference' => $reference,
        ]);

        $user->notify(new AccountActivity('Deposit received', 'We received your deposit of '.$deposit->coinLabel().' (reference '.$deposit->code().'). It is credited once the transaction is confirmed and checked.', $deposit->receiptUrl()));

        return $deposit;
    }

    /**
     * Credit a checked deposit. The admin confirms how much USDT it is worth;
     * the deposit page suggests the amount at today's price.
     */
    public function approveDeposit(Deposit $deposit, User $admin, int $creditMicros): void
    {
        if ($creditMicros <= 0) {
            throw new WalletException('Enter the USDT amount to credit.');
        }

        DB::transaction(function () use ($deposit, $admin, $creditMicros): void {
            $deposit = $this->lockPendingDeposit($deposit->id);
            $user = $this->lockUser($deposit->user_id);

            $deposit->credited_micros = $creditMicros;

            $this->apply($user, $creditMicros, LedgerType::Deposit, 'Deposit '.$deposit->code().' credited ('.$deposit->coinLabel().')', $deposit, $admin);
            $this->markReviewed($deposit, RequestStatus::Approved, $admin);

            $user->notify(new AccountActivity('Deposit credited', 'Your '.$deposit->coinLabel().' deposit was credited as '.Usdt::format($creditMicros).' USDT.', $deposit->receiptUrl(), 'success'));

            $this->payReferralBonus($user, $deposit, $admin);
        });
    }

    public function rejectDeposit(Deposit $deposit, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($deposit, $admin, $note): void {
            $locked = $this->lockPendingDeposit($deposit->id);
            $this->markReviewed($locked, RequestStatus::Rejected, $admin, $note);

            $locked->user->notify(new AccountActivity('Deposit not credited', 'Your deposit of '.$locked->coinLabel().' was rejected'.($note ? ': '.$note : '.'), $locked->receiptUrl(), 'danger'));
        });
    }

    /**
     * Hold the amount from the balance straight away so it cannot be spent twice.
     * The user picks how they are paid: USDT, or BTC/ETH/SOL converted at the
     * live price right now. The coin amount is fixed here so the admin sends exactly that.
     */
    public function requestWithdrawal(User $user, int $amountMicros, WithdrawalNetwork $network, string $address): Withdrawal
    {
        $quote = $this->quoteWithdrawal($amountMicros, $network);

        $minimum = Setting::integer('min_withdrawal_micros');

        if ($amountMicros < $minimum) {
            throw new WalletException('The smallest withdrawal is '.Usdt::format($minimum).' USDT.');
        }

        if (! in_array($network, WithdrawalNetwork::selectable(), true) || ! UsdtAddress::isValid($address, $network)) {
            throw new WalletException('Enter a valid '.$network->shortLabel().' address.');
        }

        if (Setting::enabled('withdrawal_requires_kyc') && ! $user->isKycVerified()) {
            throw new WalletException('Please verify your identity before withdrawing.');
        }

        if ($user->password_changed_at !== null && $user->password_changed_at->gt(now()->subHours(self::PASSWORD_CHANGE_HOLD_HOURS))) {
            throw new WalletException('For your safety, withdrawals are paused for 24 hours after a password change. You can withdraw again '.$user->password_changed_at->copy()->addHours(self::PASSWORD_CHANGE_HOLD_HOURS)->diffForHumans().'.');
        }

        return DB::transaction(function () use ($user, $amountMicros, $network, $address, $quote): Withdrawal {
            $locked = $this->lockUser($user->id);

            $this->ensureWithinDailyLimit($locked, $amountMicros);

            $isNewAddress = ! $locked->withdrawals()->where('address', trim($address))->exists();

            $withdrawal = $locked->withdrawals()->create([
                'amount_micros' => $amountMicros,
                'network' => $network,
                'coin' => $network->coin(),
                'coin_amount' => $quote['coin_amount'],
                'price_cents' => $quote['price_cents'],
                'address' => trim($address),
            ]);

            $this->apply($locked, -$amountMicros, LedgerType::Withdrawal, 'Withdrawal '.$withdrawal->code().' requested ('.$withdrawal->payoutLabel().')', $withdrawal);

            $user->balance_micros = $locked->balance_micros;

            $locked->notify(new AccountActivity('Withdrawal requested', 'Your withdrawal of '.$withdrawal->payoutLabel().' to '.$withdrawal->network->label().' (reference '.$withdrawal->code().') is waiting for review. The amount is on hold from your balance.', $withdrawal->receiptUrl()));

            if ($isNewAddress) {
                $locked->notify(new AccountActivity('New withdrawal address used', 'A withdrawal was requested to an address you have not used before: '.$withdrawal->address.' ('.$withdrawal->network->label().'). If this was not you, contact support right away and change your password.', $withdrawal->receiptUrl(), 'danger'));
            }

            return $withdrawal;
        });
    }

    /**
     * Refuse a withdrawal that would take today's total above the admin's daily limit.
     * Rejected withdrawals do not count. A limit of 0 means no limit.
     */
    private function ensureWithinDailyLimit(User $user, int $amountMicros): void
    {
        $limit = Setting::integer('daily_withdrawal_limit_micros');

        if ($limit <= 0) {
            return;
        }

        $usedToday = (int) $user->withdrawals()
            ->where('created_at', '>=', now()->startOfDay())
            ->where('status', '!=', RequestStatus::Rejected)
            ->sum('amount_micros');

        if ($usedToday + $amountMicros > $limit) {
            throw new WalletException('This is over the daily withdrawal limit of '.Usdt::format($limit).' USDT. You can still withdraw '.Usdt::format(max(0, $limit - $usedToday)).' USDT today.');
        }
    }

    /**
     * Mark the withdrawal as paid, after the admin has sent the coins from the
     * company wallet. The blockchain transaction ID is optional; when given it
     * must match the network, and the user gets a link to follow the payment.
     */
    public function approveWithdrawal(Withdrawal $withdrawal, User $admin, ?string $payoutTxid = null, ?string $note = null): void
    {
        $payoutTxid = filled($payoutTxid) ? $withdrawal->network->normalizeTxid($payoutTxid) : null;

        if ($payoutTxid !== null && ! $withdrawal->network->isValidTxid($payoutTxid)) {
            throw new WalletException($withdrawal->network->txidHint());
        }

        DB::transaction(function () use ($withdrawal, $admin, $payoutTxid, $note): void {
            $locked = $this->lockPendingWithdrawal($withdrawal->id);
            $locked->forceFill(['payout_txid' => $payoutTxid]);
            $this->markReviewed($locked, RequestStatus::Approved, $admin, $note);

            $locked->user->notify(new AccountActivity('Withdrawal sent', $locked->payoutLabel().' has been sent to your wallet.', $locked->receiptUrl(), 'success'));
        });
    }

    /**
     * The coin amount a withdrawal pays at today's price, or nulls for USDT.
     *
     * @return array{coin_amount: string|null, price_cents: int|null}
     */
    public function quoteWithdrawal(int $amountMicros, WithdrawalNetwork $network): array
    {
        if ($network->isUsdt()) {
            return ['coin_amount' => null, 'price_cents' => null];
        }

        $priceCents = $this->prices->priceCents($network->priceKey() ?? '');

        if ($priceCents === null || $priceCents <= 0) {
            throw new WalletException('The live '.$network->coin().' price is not available right now. Please try again in a minute or withdraw in USDT.');
        }

        return [
            'coin_amount' => str_replace(',', '', Btc::format(Usdt::toCoinUnits($amountMicros, $priceCents), fixed: true)),
            'price_cents' => $priceCents,
        ];
    }

    /**
     * Refuse the withdrawal and return the held amount to the balance.
     */
    public function rejectWithdrawal(Withdrawal $withdrawal, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $note): void {
            $withdrawal = $this->lockPendingWithdrawal($withdrawal->id);
            $user = $this->lockUser($withdrawal->user_id);

            $this->apply($user, $withdrawal->amount_micros, LedgerType::WithdrawalRefund, 'Withdrawal '.$withdrawal->code().' rejected, amount returned', $withdrawal, $admin);
            $this->markReviewed($withdrawal, RequestStatus::Rejected, $admin, $note);

            $user->notify(new AccountActivity('Withdrawal rejected', Usdt::format($withdrawal->amount_micros).' USDT is back in your balance'.($note ? '. Reason: '.$note : '.'), $withdrawal->receiptUrl(), 'danger'));
        });
    }

    /**
     * Move money from the balance into a plan.
     */
    public function invest(User $user, Plan $plan, int $amountMicros): Investment
    {
        if (! $plan->is_active) {
            throw new WalletException('This plan is not open for new investments.');
        }

        if ($amountMicros < $plan->min_micros || ($plan->max_micros !== null && $amountMicros > $plan->max_micros)) {
            throw new WalletException('Enter an amount within the plan limits.');
        }

        $goldMg = $plan->category->value === 'gold' ? $this->prices->goldMilligrams($amountMicros) : null;

        return DB::transaction(function () use ($user, $plan, $amountMicros, $goldMg): Investment {
            $locked = $this->lockUser($user->id);
            $now = now();

            $investment = $locked->investments()->create([
                'plan_id' => $plan->id,
                'amount_micros' => $amountMicros,
                'target_return_bps' => $plan->target_return_bps,
                'duration_days' => $plan->duration_days,
                'gold_mg' => $goldMg,
                'starts_at' => $now,
                'ends_at' => $now->addDays($plan->duration_days),
            ]);

            $this->apply($locked, -$amountMicros, LedgerType::Investment, 'Invested in '.$plan->name, $investment);

            $user->balance_micros = $locked->balance_micros;

            $locked->notify(new AccountActivity('Plan started', 'You invested '.Usdt::format($amountMicros).' USDT in '.$plan->name.'. It ends on '.$investment->ends_at->format('M j, Y').' and pays '.Usdt::format($amountMicros + $plan->profitFor($amountMicros)).' USDT.', route('investments'), 'success'));

            return $investment;
        });
    }

    /**
     * Bring running investments up to date: save the earnings so far and pay
     * out the ones whose term has ended. For one user, or for everyone.
     *
     * @return array{accrued: int, settled: int}
     */
    public function processDueInvestments(?User $user = null): array
    {
        $counts = ['accrued' => 0, 'settled' => 0];

        Investment::query()
            ->where('status', InvestmentStatus::Active)
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->lazyById()
            ->each(function (Investment $investment) use (&$counts): void {
                if ($investment->ends_at->isPast()) {
                    $this->settle($investment);
                    $counts['settled']++;

                    return;
                }

                $this->accrue($investment);
                $counts['accrued']++;
            });

        return $counts;
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
                $locked->earned_micros = max($locked->earned_micros, $locked->earningsAt());
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
            $earned = max($locked->earned_micros, $locked->earningsAt($locked->ends_at->isPast() ? $locked->ends_at : now()));

            $this->apply($user, $locked->amount_micros, LedgerType::InvestmentReturn, $locked->plan->name.' principal returned', $locked, $admin);

            if ($earned > 0) {
                $this->apply($user, $earned, LedgerType::Earning, $locked->plan->name.' earnings', $locked, $admin);
            }

            $locked->forceFill(['earned_micros' => $earned, 'status' => InvestmentStatus::Completed, 'settled_at' => now()])->save();

            $user->notify(new AccountActivity('Plan completed', $locked->plan->name.' finished. '.Usdt::format($locked->amount_micros + $earned).' USDT (including '.Usdt::format($earned).' USDT profit) is in your balance. Reference '.$locked->code().'.', $locked->receiptUrl(), 'success'));
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

            $this->apply($user, $locked->amount_micros, LedgerType::InvestmentReturn, $locked->plan->name.' cancelled, principal returned', $locked, $admin);

            $locked->forceFill(['earned_micros' => 0, 'status' => InvestmentStatus::Cancelled, 'settled_at' => now()])->save();

            $user->notify(new AccountActivity('Plan cancelled', $locked->plan->name.' was stopped early. Your '.Usdt::format($locked->amount_micros).' USDT is back in your balance.', route('investments')));
        });
    }

    /**
     * A manual correction by an admin, positive or negative, with a reason.
     */
    public function adjust(User $user, int $amountMicros, string $reason, User $admin): void
    {
        if ($amountMicros === 0) {
            throw new WalletException('Enter an amount other than zero.');
        }

        DB::transaction(function () use ($user, $amountMicros, $reason, $admin): void {
            $locked = $this->lockUser($user->id);
            $this->apply($locked, $amountMicros, LedgerType::Adjustment, $reason, null, $admin);

            $locked->notify($amountMicros > 0
                ? new AccountActivity('Wallet funded', Usdt::format($amountMicros).' USDT has been added to your wallet ('.$reason.'). Your balance is now '.Usdt::format($locked->balance_micros).' USDT.', route('transactions', ['view' => 'balance']), 'success')
                : new AccountActivity('Wallet debited', Usdt::format(-$amountMicros).' USDT was taken from your wallet ('.$reason.'). Your balance is now '.Usdt::format($locked->balance_micros).' USDT.', route('transactions', ['view' => 'balance'])));
        });
    }

    /**
     * Pay the referrer a share of the first approved deposit of someone they invited.
     */
    private function payReferralBonus(User $user, Deposit $deposit, User $admin): void
    {
        $bonus = intdiv((int) $deposit->credited_micros * Setting::integer('referral_bonus_bps'), 10000);

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

        $referrer->notify(new AccountActivity('Referral bonus', 'You earned '.Usdt::format($bonus).' USDT because someone you invited made their first deposit.', route('referrals'), 'success'));
    }

    /**
     * Change a locked user's balance and write the audit entry.
     */
    private function apply(User $user, int $amountMicros, LedgerType $type, string $description, ?Model $subject = null, ?User $actor = null): LedgerEntry
    {
        $newBalance = $user->balance_micros + $amountMicros;

        if ($newBalance < 0) {
            throw new WalletException('Your available balance is too low for this.');
        }

        $user->forceFill(['balance_micros' => $newBalance])->save();

        return LedgerEntry::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'amount_micros' => $amountMicros,
            'balance_after_micros' => $newBalance,
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
