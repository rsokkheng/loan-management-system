<?php

namespace App\Enums;

enum InterestMethod: string
{
    case Flat = 'flat';
    case ReducingBalance = 'reducing_balance';

    public function label(): string
    {
        return match ($this) {
            self::Flat => __('Flat Rate'),
            self::ReducingBalance => __('Reducing Balance (EMI)'),
        };
    }
}
