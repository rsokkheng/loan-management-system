<?php

namespace App\Enums;

enum JournalType: string
{
    case Disbursement = 'disbursement';
    case Repayment = 'repayment';
    case WriteOff = 'write_off';
    case Expense = 'expense';
    case Income = 'income';
    case Manual = 'manual';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Disbursement => __('Disbursement'),
            self::Repayment => __('Repayment'),
            self::WriteOff => __('Write Off'),
            self::Expense => __('Expense'),
            self::Income => __('Income'),
            self::Manual => __('Manual Entry'),
            self::Reversal => __('Reversal'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Disbursement => 'primary',
            self::Repayment => 'success',
            self::WriteOff => 'danger',
            self::Expense => 'warning',
            self::Income => 'info',
            self::Manual => 'secondary',
            self::Reversal => 'dark',
        };
    }

    /**
     * Entries created by the loan workflow cannot be reversed by hand.
     */
    public function isAutomatic(): bool
    {
        return in_array($this, [self::Disbursement, self::Repayment, self::WriteOff], true);
    }
}
