<?php

namespace App\Http\Controllers;

use App\Http\Requests\RestructureLoanRequest;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class LoanRestructureController extends Controller
{
    public function store(RestructureLoanRequest $request, Loan $loan, LoanService $loans): RedirectResponse
    {
        $loans->restructure(
            loan: $loan,
            user: $request->user(),
            newTerm: (int) $request->validated('term'),
            newInterestRate: (float) $request->validated('interest_rate'),
            firstRepaymentDate: Carbon::parse($request->validated('first_repayment_date')),
            waivePenalty: (bool) $request->validated('waive_penalty'),
            reason: $request->validated('reason'),
        );

        return back()->with('success', __('Loan :reference restructured. A new repayment schedule has been generated.', ['reference' => $loan->reference]));
    }
}
