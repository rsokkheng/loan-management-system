<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Portfolio at Risk (PAR): the outstanding principal of loans with an installment
 * overdue for more than N days, as a share of all outstanding principal.
 */
class PortfolioRiskService
{
    /**
     * PAR label => loans count when they are MORE than this many days late (PAR 30 = over 30 days).
     *
     * @var array<int, int>
     */
    public const THRESHOLDS = [1 => 0, 30 => 30, 60 => 60, 90 => 90];

    /**
     * Every active loan in the currency with its outstanding principal and days in arrears.
     *
     * @return Collection<int, array{loan: Loan, principal: float, days_late: int}>
     */
    public function loans(Currency $currency): Collection
    {
        return Loan::query()
            ->inCurrency($currency)
            ->with(['borrower', 'branch', 'officer'])
            ->where('status', LoanStatus::Active)
            ->withSum('installments as principal_due_total', 'principal_due')
            ->withSum('installments as principal_paid_total', 'principal_paid')
            ->withMin(['installments as oldest_overdue_date' => fn (Builder $query) => $query
                ->where('status', '!=', InstallmentStatus::Paid)
                ->whereDate('due_date', '<', today()),
            ], 'due_date')
            ->get()
            ->map(fn (Loan $loan) => [
                'loan' => $loan,
                'principal' => round(max(0, (float) $loan->principal_due_total - (float) $loan->principal_paid_total), 2),
                'days_late' => $loan->oldest_overdue_date
                    ? (int) Carbon::parse($loan->oldest_overdue_date)->startOfDay()->diffInDays(today())
                    : 0,
            ]);
    }

    /**
     * PAR ratios for a set of loans from {@see loans()}.
     *
     * @param  Collection<int, array{loan: Loan, principal: float, days_late: int}>  $loans
     * @return array{outstanding: float, loans: int, par: array<int, array{principal: float, loans: int, percent: float}>}
     */
    public function summarize(Collection $loans): array
    {
        $outstanding = round($loans->sum('principal'), 2);

        $par = collect(self::THRESHOLDS)->mapWithKeys(function (int $daysLate, int $label) use ($loans, $outstanding) {
            $atRisk = $loans->filter(fn (array $row) => $row['days_late'] > $daysLate);
            $principal = round($atRisk->sum('principal'), 2);

            return [$label => [
                'principal' => $principal,
                'loans' => $atRisk->count(),
                'percent' => $outstanding > 0 ? round($principal / $outstanding * 100, 2) : 0.0,
            ]];
        })->all();

        return ['outstanding' => $outstanding, 'loans' => $loans->count(), 'par' => $par];
    }
}
