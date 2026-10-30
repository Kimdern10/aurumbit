<?php

namespace App\Support;

use App\Enums\KycStatus;
use App\Enums\RequestStatus;
use App\Models\Deposit;
use App\Models\KycSubmission;
use App\Models\SupportTicket;
use App\Models\Withdrawal;

/**
 * How many requests are waiting for an admin, for the menu badges.
 */
class AdminCounts
{
    /**
     * @return array{deposits: int, withdrawals: int, kyc: int, tickets: int}
     */
    public static function pending(): array
    {
        return once(fn (): array => [
            'deposits' => Deposit::query()->where('status', RequestStatus::Pending)->count(),
            'withdrawals' => Withdrawal::query()->where('status', RequestStatus::Pending)->count(),
            'kyc' => KycSubmission::query()->where('status', KycStatus::Pending)->count(),
            'tickets' => SupportTicket::query()->where('status', 'open')->count(),
        ]);
    }
}
