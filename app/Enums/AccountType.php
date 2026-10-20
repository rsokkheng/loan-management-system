<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('Asset'),
            self::Liability => __('Liability'),
            self::Equity => __('Equity'),
            self::Income => __('Income'),
            self::Expense => __('Expense'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Asset => 'primary',
            self::Liability => 'warning',
            self::Equity => 'info',
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }

    /**
     * Assets and expenses grow with debits; the rest grow with credits.
     */
    public function isDebitNormal(): bool
    {
        return $this === self::Asset || $this === self::Expense;
    }

    /**
     * Turn a raw debit-minus-credit amount into this type's natural balance.
     */
    public function balanceFrom(float $debitMinusCredit): float
    {
        return round($this->isDebitNormal() ? $debitMinusCredit : -$debitMinusCredit, 2);
    }
}
