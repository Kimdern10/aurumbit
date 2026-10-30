<?php

namespace App\Enums;

enum PlanCategory: string
{
    case Bitcoin = 'bitcoin';
    case Gold = 'gold';

    public function label(): string
    {
        return match ($this) {
            self::Bitcoin => 'Bitcoin mining',
            self::Gold => 'Gold mining',
        };
    }
}
