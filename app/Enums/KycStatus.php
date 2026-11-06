<?php

namespace App\Enums;

enum KycStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Approved earlier, then cancelled by an admin. The user must send new documents.
     */
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Under review',
            self::Approved => 'Verified',
            self::Rejected => 'Rejected',
            self::Revoked => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected, self::Revoked => 'red',
        };
    }
}
