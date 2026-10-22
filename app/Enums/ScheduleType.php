<?php

namespace App\Enums;

enum ScheduleType: string
{
    /** Equal periods generated from the term and repayment frequency. */
    case Fixed = 'fixed';

    /** Due dates and principal amounts entered per loan (seasonal, interest-only, balloon). */
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed schedule'),
            self::Flexible => __('Flexible schedule'),
        };
    }
}
