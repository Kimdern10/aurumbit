<?php

namespace App\Console\Commands;

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
        ['accrued' => $accrued, 'settled' => $settled] = $wallet->processDueInvestments();

        $this->components->info("Updated {$accrued} active and settled {$settled} finished investments.");

        return self::SUCCESS;
    }
}
