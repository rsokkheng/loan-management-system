<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Setting;

/**
 * Decides which currency totals, reports and the ledger are shown in.
 *
 * Every loan keeps its own currency; amounts in different currencies are never
 * added together, so dashboards and reports show one currency at a time.
 */
class CurrencyContext
{
    public const SESSION_KEY = 'currency';

    public function current(): Currency
    {
        $selected = session()->isStarted() ? session(self::SESSION_KEY) : null;

        return Currency::tryFrom((string) $selected) ?? $this->default();
    }

    /**
     * The organisation's main currency, used for new products and as the reporting default.
     */
    public function default(): Currency
    {
        return Currency::tryFrom((string) Setting::get('currency_code')) ?? Currency::Usd;
    }

    public function switchTo(Currency $currency): void
    {
        session([self::SESSION_KEY => $currency->value]);
    }
}
