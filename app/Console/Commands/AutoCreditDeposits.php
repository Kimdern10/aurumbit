<?php

namespace App\Console\Commands;

use App\Services\DepositAutoCredit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:auto-credit-deposits')]
#[Description('Credit pending deposits that Bybit confirms arrived (when auto-credit is on in Site settings)')]
class AutoCreditDeposits extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DepositAutoCredit $autoCredit): int
    {
        if (! DepositAutoCredit::isEnabled()) {
            $this->components->info('Auto-credit is off. Turn it on in Site settings.');

            return self::SUCCESS;
        }

        $counts = $autoCredit->run();

        $this->components->info("Credited {$counts['credited']} deposit(s). {$counts['left']} left for an admin to review.");

        return self::SUCCESS;
    }
}
