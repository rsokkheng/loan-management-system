<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Services\BranchContext;
use App\Services\CurrencyContext;
use App\Services\PortfolioRiskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(PortfolioRiskService $risk, CurrencyContext $currencies): View
    {
        $currency = $currencies->current();
        $activeLoans = Loan::query()->inCurrency($currency)->withStatus(LoanStatus::Active);

        $outstanding = (float) (clone $activeLoans)->toBase()
            ->selectRaw('COALESCE(SUM(total_payable + total_penalty - amount_paid), 0) as aggregate')
            ->value('aggregate');

        $overdueInstallments = LoanInstallment::query()
            ->inCurrency($currency)
            ->overdue()
            ->whereHas('loan', fn (Builder $query) => $query->where('status', LoanStatus::Active));

        $overdueAmount = (float) (clone $overdueInstallments)->toBase()
            ->selectRaw('COALESCE(SUM(principal_due + interest_due + penalty_due - principal_paid - interest_paid - penalty_paid), 0) as aggregate')
            ->value('aggregate');

        $stats = [
            'borrowers' => Borrower::query()->count(),
            'active_loans' => (clone $activeLoans)->count(),
            'pending_loans' => Loan::query()->inCurrency($currency)->withStatus(LoanStatus::Pending, LoanStatus::Approved)->count(),
            'outstanding' => $outstanding,
            'total_disbursed' => (float) Loan::query()->inCurrency($currency)->whereNotNull('disbursed_at')->sum('principal_amount'),
            'collected_this_month' => (float) Payment::query()->inCurrency($currency)->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'overdue_amount' => $overdueAmount,
            'overdue_loans' => Loan::query()->inCurrency($currency)->overdue()->count(),
            'portfolio_at_risk' => round($risk->summarize($risk->loans($currency))['par'][30]['percent'], 1),
        ];

        return view('dashboard', [
            'stats' => $stats,
            'branchSummary' => $this->branchSummary($currency),
            'chart' => $this->monthlyTrend($currency),
            'statusBreakdown' => Loan::query()
                ->inCurrency($currency)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'upcomingInstallments' => LoanInstallment::query()
                ->inCurrency($currency)
                ->with('loan.borrower')
                ->where('status', '!=', 'paid')
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->whereHas('loan', fn (Builder $query) => $query->where('status', LoanStatus::Active))
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'overdueList' => (clone $overdueInstallments)
                ->with('loan.borrower')
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'recentPayments' => Payment::query()
                ->inCurrency($currency)
                ->with('loan.borrower')
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Side-by-side branch figures, shown when viewing all branches.
     *
     * @return Collection<int, Branch>|null
     */
    private function branchSummary(Currency $currency): ?Collection
    {
        if (app(BranchContext::class)->id() !== null) {
            return null;
        }

        $active = fn ($query) => $query->withoutGlobalScopes()->where('currency', $currency)->where('status', LoanStatus::Active);

        return Branch::query()
            ->active()
            ->withCount(['loans as active_loans' => $active])
            ->withSum(['loans as outstanding' => $active], DB::raw('total_payable + total_penalty - amount_paid'))
            ->withSum(['loans as disbursed' => fn ($query) => $query->withoutGlobalScopes()->where('currency', $currency)->whereNotNull('disbursed_at')], 'principal_amount')
            ->orderBy('name')
            ->get();
    }

    /**
     * Disbursements vs collections for the last 12 months.
     *
     * @return array{labels: list<string>, disbursed: list<float>, collected: list<float>}
     */
    private function monthlyTrend(Currency $currency): array
    {
        $start = now()->subMonths(11)->startOfMonth();

        $disbursed = Loan::query()
            ->inCurrency($currency)
            ->whereDate('disbursed_at', '>=', $start)
            ->get(['disbursed_at', 'principal_amount'])
            ->groupBy(fn (Loan $loan) => $loan->disbursed_at->format('Y-m'))
            ->map(fn ($loans) => round((float) $loans->sum('principal_amount'), 2));

        $collected = Payment::query()
            ->inCurrency($currency)
            ->whereDate('payment_date', '>=', $start)
            ->get(['payment_date', 'amount'])
            ->groupBy(fn (Payment $payment) => $payment->payment_date->format('Y-m'))
            ->map(fn ($payments) => round((float) $payments->sum('amount'), 2));

        $months = collect(range(0, 11))->map(fn (int $offset) => $start->copy()->addMonths($offset));

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->format('M Y'))->all(),
            'disbursed' => $months->map(fn (Carbon $month) => $disbursed->get($month->format('Y-m'), 0))->all(),
            'collected' => $months->map(fn (Carbon $month) => $collected->get($month->format('Y-m'), 0))->all(),
        ];
    }
}
