<?php

namespace App\Services;

use App\Data\LoanQuote;
use App\Data\ScheduleLine;
use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class LoanCalculator
{
    /**
     * Build a full repayment schedule for the given loan terms.
     *
     * @param  float  $annualRate  Annual interest rate in percent (e.g. 18 for 18%).
     * @param  int  $term  Number of installments.
     * @param  int  $precision  Decimal places amounts are rounded to (0 for riel).
     */
    public function quote(
        float $principal,
        float $annualRate,
        int $term,
        InterestMethod $method,
        RepaymentFrequency $frequency,
        CarbonInterface $firstDueDate,
        float $processingFeePercent = 0,
        int $precision = 2,
    ): LoanQuote {
        if ($principal <= 0 || $term < 1 || $annualRate < 0) {
            throw new InvalidArgumentException('Principal and term must be positive and the rate cannot be negative.');
        }

        $periodicRate = $annualRate / 100 / $frequency->periodsPerYear();

        $lines = match ($method) {
            InterestMethod::Flat => $this->flatSchedule($principal, $periodicRate, $term, $precision),
            InterestMethod::ReducingBalance => $this->reducingSchedule($principal, $periodicRate, $term, $precision),
        };

        $schedule = collect($lines)->map(fn (array $line, int $index) => new ScheduleLine(
            number: $index + 1,
            dueDate: $frequency->dueDateFor($firstDueDate, $index + 1),
            principal: $line['principal'],
            interest: $line['interest'],
            balanceAfter: $line['balance'],
        ));

        return new LoanQuote(
            principal: round($principal, $precision),
            totalInterest: round($schedule->sum('interest'), $precision),
            processingFee: round($principal * $processingFeePercent / 100, $precision),
            schedule: $schedule,
        );
    }

    /**
     * Build a schedule from due dates and principal amounts chosen per loan
     * (seasonal, interest-only or balloon repayments). Interest accrues by the
     * actual days in each period on a 365-day year: on the remaining balance
     * for reducing-balance loans, or on the original principal for flat loans.
     *
     * @param  list<array{due_date: string|CarbonInterface, principal: float|int|string}>  $plan
     */
    public function flexibleQuote(
        float $principal,
        float $annualRate,
        InterestMethod $method,
        CarbonInterface $startDate,
        array $plan,
        float $processingFeePercent = 0,
        int $precision = 2,
    ): LoanQuote {
        if ($principal <= 0 || $plan === [] || $annualRate < 0) {
            throw new InvalidArgumentException('A flexible schedule needs a positive principal and at least one installment.');
        }

        $dailyRate = $annualRate / 100 / 365;
        $balance = round($principal, $precision);
        $periodStart = $startDate->copy()->startOfDay();
        $lines = collect();

        foreach (array_values($plan) as $index => $row) {
            $dueDate = Carbon::parse($row['due_date'])->startOfDay();
            $isLast = $index === count($plan) - 1;
            $principalPaid = $isLast ? $balance : min($balance, round((float) $row['principal'], $precision));
            $days = (int) max(0, $periodStart->diffInDays($dueDate));
            $interestBase = $method === InterestMethod::Flat ? $principal : $balance;

            $lines->push(new ScheduleLine(
                number: $index + 1,
                dueDate: $dueDate,
                principal: $principalPaid,
                interest: round($interestBase * $dailyRate * $days, $precision),
                balanceAfter: max(0, round($balance - $principalPaid, $precision)),
            ));

            $balance = round($balance - $principalPaid, $precision);
            $periodStart = $dueDate;
        }

        return new LoanQuote(
            principal: round($principal, $precision),
            totalInterest: round($lines->sum('interest'), $precision),
            processingFee: round($principal * $processingFeePercent / 100, $precision),
            schedule: $lines,
        );
    }

    /**
     * Interest is charged on the original principal for every period.
     *
     * @return list<array{principal: float, interest: float, balance: float}>
     */
    private function flatSchedule(float $principal, float $periodicRate, int $term, int $precision): array
    {
        $totalInterest = round($principal * $periodicRate * $term, $precision);
        $principalPart = round($principal / $term, $precision);
        $interestPart = round($totalInterest / $term, $precision);

        $lines = [];
        $balance = $principal;
        $interestAllocated = 0.0;

        for ($i = 1; $i <= $term; $i++) {
            $isLast = $i === $term;
            $principalPaid = $isLast ? round($balance, $precision) : $principalPart;
            $interest = $isLast ? round($totalInterest - $interestAllocated, $precision) : $interestPart;

            $balance = round($balance - $principalPaid, $precision);
            $interestAllocated += $interest;

            $lines[] = ['principal' => $principalPaid, 'interest' => $interest, 'balance' => max(0, $balance)];
        }

        return $lines;
    }

    /**
     * Equal installments (EMI) with interest charged on the remaining balance.
     *
     * @return list<array{principal: float, interest: float, balance: float}>
     */
    private function reducingSchedule(float $principal, float $periodicRate, int $term, int $precision): array
    {
        $installment = $periodicRate > 0
            ? round($principal * $periodicRate / (1 - (1 + $periodicRate) ** -$term), $precision)
            : round($principal / $term, $precision);

        $lines = [];
        $balance = $principal;

        for ($i = 1; $i <= $term; $i++) {
            $interest = round($balance * $periodicRate, $precision);
            $principalPaid = $i === $term ? round($balance, $precision) : round($installment - $interest, $precision);
            $balance = round($balance - $principalPaid, $precision);

            $lines[] = ['principal' => $principalPaid, 'interest' => $interest, 'balance' => max(0, $balance)];
        }

        return $lines;
    }
}
