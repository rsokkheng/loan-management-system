<?php

namespace App\Http\Controllers;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Models\LoanProduct;
use App\Services\LoanCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalculatorController extends Controller
{
    public function __invoke(Request $request, LoanCalculator $calculator): View
    {
        $quote = null;

        if ($request->filled('principal')) {
            $input = $request->validate([
                'principal' => ['required', 'numeric', 'min:1', 'max:1000000000'],
                'interest_rate' => ['required', 'numeric', 'min:0', 'max:500'],
                'term' => ['required', 'integer', 'min:1', 'max:1000'],
                'interest_method' => ['required', Rule::enum(InterestMethod::class)],
                'repayment_frequency' => ['required', Rule::enum(RepaymentFrequency::class)],
                'first_due_date' => ['required', 'date'],
                'processing_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ]);

            $quote = $calculator->quote(
                principal: (float) $input['principal'],
                annualRate: (float) $input['interest_rate'],
                term: (int) $input['term'],
                method: InterestMethod::from($input['interest_method']),
                frequency: RepaymentFrequency::from($input['repayment_frequency']),
                firstDueDate: Carbon::parse($input['first_due_date']),
                processingFeePercent: (float) ($input['processing_fee_percent'] ?? 0),
            );
        }

        return view('calculator', [
            'quote' => $quote,
            'products' => LoanProduct::query()->active()->orderBy('name')->get(),
            'interestMethods' => InterestMethod::cases(),
            'frequencies' => RepaymentFrequency::cases(),
        ]);
    }
}
