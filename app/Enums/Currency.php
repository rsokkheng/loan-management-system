<?php

namespace App\Enums;

use App\Services\CurrencyContext;

enum Currency: string
{
    case Usd = 'USD';
    case Khr = 'KHR';

    public function label(): string
    {
        return match ($this) {
            self::Usd => __('US Dollar'),
            self::Khr => __('Khmer Riel'),
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Usd => '$',
            self::Khr => '៛',
        };
    }

    /**
     * Decimal places amounts are calculated and shown with (riel has no cents).
     */
    public function precision(): int
    {
        return match ($this) {
            self::Usd => 2,
            self::Khr => 0,
        };
    }

    /**
     * Smallest amount a form should accept, e.g. 0.01 for dollars and 1 for riel.
     */
    public function step(): string
    {
        return $this->precision() > 0 ? '0.'.str_repeat('0', $this->precision() - 1).'1' : '1';
    }

    public function round(float|int|string|null $amount): float
    {
        return round((float) $amount, $this->precision());
    }

    /**
     * Format an amount as "$1,234.50" or "1,234,500៛".
     */
    public function format(float|int|string|null $amount): string
    {
        $number = number_format((float) $amount, $this->precision());

        return match ($this) {
            self::Usd => (str_starts_with($number, '-') ? '-$'.ltrim($number, '-') : '$'.$number),
            self::Khr => $number.'៛',
        };
    }

    /**
     * Format an amount in the given currency, or in the reporting currency when none is given.
     */
    public static function display(float|int|string|null $amount, ?self $currency = null): string
    {
        return ($currency ?? app(CurrencyContext::class)->current())->format($amount);
    }
}
