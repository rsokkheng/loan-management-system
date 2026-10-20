<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\LoanRequest;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function __construct(private LoanService $loans) {}

    public function index(Request $request): View
    {
        $status = LoanStatus::tryFrom((string) $request->input('status'));

        $loans = Loan::query()
            ->with(['borrower', 'product', 'branch'])
            ->search($request->string('search')->trim()->value())
            ->when($status, fn ($query) => $query->withStatus($status))
            ->when($request->input('status') === 'overdue', fn ($query) => $query->overdue())
            ->when($request->integer('product'), fn ($query, int $productId) => $query->where('loan_product_id', $productId))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('loans.index', [
            'loans' => $loans,
            'statuses' => LoanStatus::cases(),
            'products' => LoanProduct::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('loans.create', $this->formData(new Loan([
            'borrower_id' => $request->integer('borrower') ?: null,
            'application_date' => today(),
            'loan_officer_id' => $request->user()->id,
        ])));
    }

    public function store(LoanRequest $request): RedirectResponse
    {
        $loan = $this->loans->apply($request->validated(), $request->user());

        return redirect()->route('loans.show', $loan)->with('success', __('Loan application :reference submitted for approval.', ['reference' => $loan->reference]));
    }

    public function show(Loan $loan): View
    {
        $loan->load(['branch', 'borrower', 'product', 'officer', 'approver', 'rejecter', 'disburser', 'installments', 'payments.receiver']);

        $preview = $loan->status === LoanStatus::Pending || $loan->status === LoanStatus::Approved
            ? $this->loans->quoteFor($loan, $loan->repayment_frequency->addPeriods(today(), 1))
            : null;

        return view('loans.show', [
            'loan' => $loan,
            'preview' => $preview,
            'paymentMethods' => PaymentMethod::cases(),
            'overdueBalance' => $loan->installments->filter->isOverdue()->sum('balance'),
        ]);
    }

    public function edit(Loan $loan): View|RedirectResponse
    {
        if (! $loan->isEditable()) {
            return redirect()->route('loans.show', $loan)->with('error', __('Only pending applications can be edited.'));
        }

        return view('loans.edit', $this->formData($loan->load('borrower')));
    }

    public function update(LoanRequest $request, Loan $loan): RedirectResponse
    {
        $this->loans->update($loan, $request->validated());

        return redirect()->route('loans.show', $loan)->with('success', __('Loan application updated.'));
    }

    public function destroy(Loan $loan): RedirectResponse
    {
        Gate::authorize('delete-records');

        if (! in_array($loan->status, [LoanStatus::Pending, LoanStatus::Rejected], true)) {
            return back()->with('error', __('Only pending or rejected applications can be deleted.'));
        }

        $loan->delete();

        ActivityLog::record('deleted', "Loan {$loan->reference} deleted", $loan);

        return redirect()->route('loans.index')->with('success', __('Loan application deleted.'));
    }

    /**
     * Printable loan agreement summary and repayment schedule.
     */
    public function print(Loan $loan): View
    {
        $loan->load(['branch', 'borrower', 'product', 'officer', 'installments']);

        return view('loans.print', compact('loan'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Loan $loan): array
    {
        return [
            'loan' => $loan,
            'borrowers' => Borrower::query()
                ->where('is_blacklisted', false)
                ->orderBy('first_name')
                ->get(['id', 'code', 'first_name', 'last_name']),
            'products' => LoanProduct::query()->active()->orderBy('name')->get(),
            'officers' => User::query()
                ->where('is_active', true)
                ->when(app(BranchContext::class)->id(), fn ($query, int $branchId) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->pluck('name', 'id'),
        ];
    }
}
