<?php

namespace App\Enums;

enum CollateralStatus: string
{
    case Held = 'held';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Held => __('Held'),
            self::Released => __('Released'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Held => 'primary',
            self::Released => 'secondary',
        };
    }
}
