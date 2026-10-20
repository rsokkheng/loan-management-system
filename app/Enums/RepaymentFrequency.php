<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum RepaymentFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Biweekly => __('Every 2 Weeks'),
            self::Monthly => __('Monthly'),
        };
    }

    /**
     * Singular noun for one repayment period, e.g. "month".
     */
    public function periodName(): string
    {
        return match ($this) {
            self::Daily => 'day',
            self::Weekly => 'week',
            self::Biweekly => 'fortnight',
            self::Monthly => 'month',
        };
    }

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Daily => 365,
            self::Weekly => 52,
            self::Biweekly => 26,
            self::Monthly => 12,
        };
    }

    /**
     * Get the due date of the given installment, counting from the first due date.
     */
    public function dueDateFor(CarbonInterface $firstDueDate, int $installmentNumber): CarbonInterface
    {
        return $this->addPeriods($firstDueDate, $installmentNumber - 1);
    }

    /**
     * Move a date forward by a number of repayment periods.
     */
    public function addPeriods(CarbonInterface $date, int $periods): CarbonInterface
    {
        return match ($this) {
            self::Daily => $date->copy()->addDays($periods),
            self::Weekly => $date->copy()->addWeeks($periods),
            self::Biweekly => $date->copy()->addWeeks($periods * 2),
            self::Monthly => $date->copy()->addMonthsNoOverflow($periods),
        };
    }
}
