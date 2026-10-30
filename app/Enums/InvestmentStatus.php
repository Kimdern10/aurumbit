<?php

namespace App\Enums;

enum InvestmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'sky',
            self::Completed => 'green',
            self::Cancelled => 'zinc',
        };
    }
}
