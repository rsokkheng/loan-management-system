<?php

namespace App\Http\Controllers;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function collections(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);

        $payments = Payment::query()
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
     * Portfolio summary per loan product.
     */
    public function portfolio(): View
    {
        $products = LoanProduct::query()
            ->withCount(['loans as active_count' => fn (Builder $query) => $query->where('status', LoanStatus::Active)])
            ->withCount(['loans as closed_count' => fn (Builder $query) => $query->where('status', LoanStatus::Closed)])
            ->withSum(['loans as disbursed_total' => fn (Builder $query) => $query->whereNotNull('disbursed_at')], 'principal_amount')
            ->withSum(['loans as collected_total' => fn (Builder $query) => $query->whereNotNull('disbursed_at')], 'amount_paid')
            ->withSum(['loans as interest_total' => fn (Builder $query) => $query->whereNotNull('disbursed_at')], 'total_interest')
            ->orderBy('name')
            ->get();

        $outstanding = Loan::query()
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
