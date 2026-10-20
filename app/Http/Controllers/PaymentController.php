<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\PaymentRequest;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\RepaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $payments = Payment::query()
            ->with(['loan.borrower', 'receiver'])
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('loan', fn ($query) => $query->search($search));
            }))
            ->when($request->date('from'), fn ($query, $from) => $query->whereDate('payment_date', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->whereDate('payment_date', '<=', $to))
            ->when(PaymentMethod::tryFrom((string) $request->input('method')), fn ($query, $method) => $query->where('method', $method))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'methods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Pick an active loan to receive a payment for.
     */
    public function create(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $loans = Loan::query()
            ->with('borrower')
            ->withStatus(LoanStatus::Active)
            ->search($search)
            ->orderBy('reference')
            ->paginate(15)
            ->withQueryString();

        return view('payments.create', compact('loans', 'search'));
    }

    public function store(PaymentRequest $request, Loan $loan, RepaymentService $repayments): RedirectResponse
    {
        $payment = $repayments->record(
            loan: $loan,
            amount: (float) $request->validated('amount'),
            paymentDate: Carbon::parse($request->validated('payment_date')),
            method: PaymentMethod::from($request->validated('method')),
            receiver: $request->user(),
            transactionReference: $request->validated('transaction_reference'),
            notes: $request->validated('notes'),
        );

        return redirect()->route('payments.show', $payment)->with('success', __('Payment recorded. Receipt :receipt.', ['receipt' => $payment->receipt_number]));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['loan.borrower', 'receiver']);

        return view('payments.show', compact('payment'));
    }

    public function receipt(Payment $payment): View
    {
        $payment->load(['loan.borrower', 'receiver']);

        return view('payments.receipt', compact('payment'));
    }
}
