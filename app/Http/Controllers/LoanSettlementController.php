<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\SettleLoanRequest;
use App\Models\Loan;
use App\Services\RepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Early settlement: the borrower pays the loan off in full before maturity.
 */
class LoanSettlementController extends Controller
{
    public function __construct(private RepaymentService $repayments) {}

    public function create(Request $request, Loan $loan): View|RedirectResponse
    {
        if ($loan->status !== LoanStatus::Active) {
            return redirect()->route('loans.show', $loan)->with('error', __('Only active loans can be settled.'));
        }

        $settlementDate = $request->date('date') ?? today();

        if ($settlementDate->isFuture() || $settlementDate->lt($loan->disbursed_at)) {
            $settlementDate = today();
        }

        $loan->load('borrower');

        return view('loans.settle', [
            'loan' => $loan,
            'quote' => $this->repayments->settlementQuote($loan, $settlementDate),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function store(SettleLoanRequest $request, Loan $loan): RedirectResponse
    {
        $settlementDate = Carbon::parse($request->validated('payment_date'));
        $quote = $this->repayments->settlementQuote($loan, $settlementDate);

        if (round((float) $request->validated('expected_total'), 2) !== $quote->total()) {
            return redirect()->route('loans.settle', ['loan' => $loan, 'date' => $settlementDate->toDateString()])
                ->with('error', __('The settlement amount has changed. Please review the new amount and confirm again.'));
        }

        $payment = $this->repayments->settle(
            loan: $loan,
            settlementDate: $settlementDate,
            method: PaymentMethod::from($request->validated('method')),
            receiver: $request->user(),
            transactionReference: $request->validated('transaction_reference'),
            notes: $request->validated('notes'),
        );

        return redirect()->route('payments.show', $payment)->with('success', __('Loan :reference settled in full. Receipt :receipt.', ['reference' => $loan->reference, 'receipt' => $payment->receipt_number]));
    }
}
