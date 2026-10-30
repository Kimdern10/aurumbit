<?php

namespace App\Console\Commands;

use App\Enums\InvestmentStatus;
use App\Models\Investment;
use App\Services\WalletService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-investments')]
#[Description('Update earnings of active investments and settle the ones that have finished')]
class ProcessInvestments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WalletService $wallet): int
    {
        $accrued = 0;
        $settled = 0;

        Investment::query()
            ->where('status', InvestmentStatus::Active)
            ->lazyById()
            ->each(function (Investment $investment) use ($wallet, &$accrued, &$settled): void {
                if ($investment->ends_at->isPast()) {
                    $wallet->settle($investment);
                    $settled++;

                    return;
                }

                $wallet->accrue($investment);
                $accrued++;
            });

        $this->components->info("Updated {$accrued} active and settled {$settled} finished investments.");

        return self::SUCCESS;
    }
}
