<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\DisburseLoanRequest;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LoanWorkflowController extends Controller
{
    public function __construct(private LoanService $loans) {}

    public function approve(Request $request, Loan $loan): RedirectResponse
    {
        $this->loans->approve($loan, $request->user());

        return back()->with('success', __('Loan :reference approved. It is now ready for disbursement.', ['reference' => $loan->reference]));
    }

    public function returnForCorrection(Request $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validate(['return_reason' => ['required', 'string', 'max:1000']]);

        $this->loans->returnForCorrection($loan, $request->user(), $validated['return_reason']);

        return back()->with('success', __('Loan :reference returned to the loan officer for correction.', ['reference' => $loan->reference]));
    }

    public function resubmit(Loan $loan): RedirectResponse
    {
        $this->loans->resubmit($loan);

        return back()->with('success', __('Loan :reference resubmitted for approval.', ['reference' => $loan->reference]));
    }

    public function reject(Request $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        $this->loans->reject($loan, $request->user(), $validated['rejection_reason']);

        return back()->with('success', __('Loan :reference rejected.', ['reference' => $loan->reference]));
    }

    public function disburse(DisburseLoanRequest $request, Loan $loan): RedirectResponse
    {
        $this->loans->disburse(
            loan: $loan,
            disburser: $request->user(),
            disbursedAt: Carbon::parse($request->validated('disbursed_at')),
            firstRepaymentDate: Carbon::parse($request->validated('first_repayment_date')),
            method: PaymentMethod::from($request->validated('disbursement_method')),
        );

        return back()->with('success', __('Loan :reference disbursed and repayment schedule generated.', ['reference' => $loan->reference]));
    }

    public function writeOff(Request $request, Loan $loan): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->loans->writeOff($loan, $validated['reason']);

        return back()->with('success', __('Loan :reference written off.', ['reference' => $loan->reference]));
    }
}
