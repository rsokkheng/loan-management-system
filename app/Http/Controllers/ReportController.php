<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Services\AccountingReportService;
use App\Services\BranchContext;
use App\Services\CurrencyContext;
use App\Services\PortfolioRiskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports cover one currency at a time: the reporting currency chosen in the top bar.
 */
class ReportController extends Controller
{
    public function __construct(private CurrencyContext $currencies) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function collections(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $payments = Payment::query()
            ->inCurrency($this->currencies->current())
            ->with(['loan.borrower', 'receiver'])
            ->whereBetween('payment_date', [$from, $to])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        if ($request->input('export') === 'csv') {
            return $this->csv("collections-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Date', 'Receipt', 'Loan', 'Borrower', 'Method', 'Principal', 'Interest', 'Penalty', 'Total', 'Received By'],
                $payments->map(fn (Payment $payment) => [
                    $payment->payment_date->toDateString(),
                    $payment->receipt_number,
                    $payment->loan->reference,
                    $payment->loan->borrower->full_name,
                    $payment->method->label(),
                    $payment->principal_amount,
                    $payment->interest_amount,
                    $payment->penalty_amount,
                    $payment->amount,
                    $payment->receiver?->name,
                ]),
            );
        }

        return view('reports.collections', compact('payments', 'from', 'to'));
    }

    public function disbursements(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $loans = Loan::query()
            ->inCurrency($this->currencies->current())
            ->with(['borrower', 'product', 'disburser'])
            ->whereBetween('disbursed_at', [$from, $to])
            ->orderBy('disbursed_at')
            ->orderBy('id')
            ->get();

        if ($request->input('export') === 'csv') {
            return $this->csv("disbursements-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Date', 'Loan', 'Borrower', 'Product', 'Principal', 'Processing Fee', 'Interest', 'Total Payable', 'Status'],
                $loans->map(fn (Loan $loan) => [
                    $loan->disbursed_at->toDateString(),
                    $loan->reference,
                    $loan->borrower->full_name,
                    $loan->product->name,
                    $loan->principal_amount,
                    $loan->processing_fee,
                    $loan->total_interest,
                    $loan->total_payable,
                    $loan->status->label(),
                ]),
            );
        }

        return view('reports.disbursements', compact('loans', 'from', 'to'));
    }

    /**
     * Overdue installments grouped into aging buckets.
     */
    public function arrears(Request $request): View|StreamedResponse
    {
        $installments = LoanInstallment::query()
            ->inCurrency($this->currencies->current())
            ->with('loan.borrower')
            ->overdue()
            ->whereHas('loan', fn (Builder $query) => $query->where('status', LoanStatus::Active))
            ->orderBy('due_date')
            ->get();

        $rows = $installments
            ->groupBy('loan_id')
            ->map(function (Collection $items) {
                $oldest = $items->first();

                return [
                    'loan' => $oldest->loan,
                    'installments' => $items->count(),
                    'oldest_due' => $oldest->due_date,
                    'days_overdue' => $oldest->days_overdue,
                    'amount' => round($items->sum('balance'), 2),
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();

        $buckets = collect(['1-30' => [1, 30], '31-60' => [31, 60], '61-90' => [61, 90], '90+' => [91, PHP_INT_MAX]])
            ->map(fn (array $range) => $rows->filter(fn (array $row) => $row['days_overdue'] >= $range[0] && $row['days_overdue'] <= $range[1]))
            ->map(fn (Collection $group) => ['loans' => $group->count(), 'amount' => round($group->sum('amount'), 2)]);

        if ($request->input('export') === 'csv') {
            return $this->csv('arrears-'.today()->toDateString().'.csv',
                ['Loan', 'Borrower', 'Phone', 'Overdue Installments', 'Oldest Due Date', 'Days Overdue', 'Amount Overdue'],
                $rows->map(fn (array $row) => [
                    $row['loan']->reference,
                    $row['loan']->borrower->full_name,
                    $row['loan']->borrower->phone,
                    $row['installments'],
                    $row['oldest_due']->toDateString(),
                    $row['days_overdue'],
                    $row['amount'],
                ]),
            );
        }

        return view('reports.arrears', compact('rows', 'buckets'));
    }

    /**
     * Portfolio at Risk (PAR 1/30/60/90) overall, per branch and per loan officer.
     */
    public function portfolioAtRisk(Request $request, PortfolioRiskService $risk): View|StreamedResponse
    {
        $loans = $risk->loans($this->currencies->current());

        if ($request->input('export') === 'csv') {
            return $this->csv('portfolio-at-risk-'.today()->toDateString().'.csv',
                ['Loan', 'Borrower', 'Branch', 'Loan Officer', 'Outstanding Principal', 'Days Late'],
                $loans->sortByDesc('days_late')->map(fn (array $row) => [
                    $row['loan']->reference,
                    $row['loan']->borrower->full_name,
                    $row['loan']->branch?->name,
                    $row['loan']->officer?->name,
                    $row['principal'],
                    $row['days_late'],
                ]),
            );
        }

        $groupBy = fn (callable $key) => $loans
            ->groupBy($key)
            ->map(fn (Collection $group, string $name) => ['name' => $name] + $risk->summarize($group))
            ->sortByDesc(fn (array $summary) => $summary['par'][30]['percent'])
            ->values();

        return view('reports.portfolio-at-risk', [
            'summary' => $risk->summarize($loans),
            'byBranch' => $groupBy(fn (array $row) => $row['loan']->branch?->name ?? __('No branch')),
            'byOfficer' => $groupBy(fn (array $row) => $row['loan']->officer?->name ?? __('Unassigned')),
            'atRiskLoans' => $loans->filter(fn (array $row) => $row['days_late'] > 30)->sortByDesc('days_late')->values(),
        ]);
    }

    /**
     * Profit and loss per branch for a period, alongside each branch's portfolio.
     */
    public function branchProfitability(Request $request, AccountingReportService $accounting, PortfolioRiskService $risk, BranchContext $branches): View|StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $currency = $this->currencies->current();

        $profit = $accounting->profitByBranch($from, $to);
        $portfolio = $risk->loans($currency)->groupBy(fn (array $row) => (int) $row['loan']->branch_id);
        $disbursed = Loan::query()
            ->inCurrency($currency)
            ->whereBetween('disbursed_at', [$from, $to])
            ->selectRaw('branch_id, SUM(principal_amount) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $names = Branch::query()
            ->when($branches->id(), fn ($query, int $id) => $query->whereKey($id))
            ->orderBy('name')
            ->pluck('name', 'id');

        if ($profit->has(0) || $portfolio->has(0)) {
            $names->put(0, __('Head office / unassigned'));
        }

        $empty = array_fill_keys(['interest', 'fees', 'penalty', 'recovery', 'other_income', 'loan_loss', 'operating', 'income', 'expenses', 'net'], 0.0);

        $rows = $names->map(function (string $name, int $branchId) use ($profit, $portfolio, $disbursed, $risk, $empty) {
            $figures = $profit->get($branchId, $empty);
            $summary = $risk->summarize($portfolio->get($branchId, collect()));

            return ['name' => $name] + $figures + [
                'margin' => $figures['income'] > 0 ? round($figures['net'] / $figures['income'] * 100, 1) : null,
                'active_loans' => $summary['loans'],
                'outstanding' => $summary['outstanding'],
                'par30' => $summary['par'][30]['percent'],
                'disbursed' => round((float) $disbursed->get($branchId, 0), 2),
                'return_on_portfolio' => $summary['outstanding'] > 0 ? round($figures['net'] / $summary['outstanding'] * 100, 2) : null,
            ];
        })->sortByDesc('net')->values();

        if ($request->input('export') === 'csv') {
            return $this->csv("branch-profitability-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Branch', 'Interest', 'Fees', 'Penalty', 'Recovery', 'Other Income', 'Total Income', 'Loan Loss', 'Operating Expenses', 'Total Expenses', 'Net Profit', 'Margin %', 'Active Loans', 'Outstanding Principal', 'PAR 30 %', 'Disbursed'],
                $rows->map(fn (array $row) => [
                    $row['name'], $row['interest'], $row['fees'], $row['penalty'], $row['recovery'], $row['other_income'], $row['income'],
                    $row['loan_loss'], $row['operating'], $row['expenses'], $row['net'], $row['margin'], $row['active_loans'],
                    $row['outstanding'], $row['par30'], $row['disbursed'],
                ]),
            );
        }

        $totals = collect(array_keys($empty))->mapWithKeys(fn (string $key) => [$key => round($rows->sum($key), 2)])->all();

        return view('reports.branch-profitability', compact('rows', 'totals', 'from', 'to'));
    }

    /**
     * Portfolio summary per loan product.
     */
    public function portfolio(): View
    {
        $products = LoanProduct::query()
            ->where('currency', $this->currencies->current())
            ->withCount(['loans as active_count' => fn (Builder $query) => $query->where('status', LoanStatus::Active)])
            ->withCount(['loans as closed_count' => fn (Builder $query) => $query->where('status', LoanStatus::Closed)])
            ->withSum(['loans as disbursed_total' => fn (Builder $query) => $query->whereNotNull('disbursed_at')], 'principal_amount')
            ->withSum(['loans as collected_total' => fn (Builder $query) => $query->whereNotNull('disbursed_at')], 'amount_paid')
            ->withSum(['loans as interest_total' => fn (Builder $query) => $query->whereNotNull('disbursed_at')], 'total_interest')
            ->orderBy('name')
            ->get();

        $outstanding = Loan::query()
            ->inCurrency($this->currencies->current())
            ->where('status', LoanStatus::Active)
            ->selectRaw('loan_product_id, SUM(total_payable + total_penalty - amount_paid) as balance')
            ->groupBy('loan_product_id')
            ->pluck('balance', 'loan_product_id');

        return view('reports.portfolio', compact('products', 'outstanding'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? today();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /**
     * @param  list<string>  $headers
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
