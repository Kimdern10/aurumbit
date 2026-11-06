<?php

namespace App\Enums;

/**
 * What an admin may do. Support staff can see everything and answer users,
 * but only owners can move money or change how the site works.
 */
enum AdminRole: string
{
    case Owner = 'owner';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Support => 'Support',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access: approves deposits and withdrawals, adjusts balances, and changes plans, wallets and settings.',
            self::Support => 'Sees users and requests, answers support and checks identities. Cannot move money or change settings.',
        };
    }
}
