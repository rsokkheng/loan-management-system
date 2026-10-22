<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Services\CurrencyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    /**
     * Switch the currency that dashboards, reports and the ledger are shown in.
     */
    public function __invoke(Request $request, CurrencyContext $context): RedirectResponse
    {
        $validated = $request->validate(['currency' => ['required', Rule::enum(Currency::class)]]);

        $context->switchTo(Currency::from($validated['currency']));

        return back();
    }
}
