<?php

namespace App\Services;

use App\Data\LoanQuote;
use App\Data\ScheduleLine;
use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class LoanCalculator
{
    /**
     * Build a full repayment schedule for the given loan terms.
     *
     * @param  float  $annualRate  Annual interest rate in percent (e.g. 18 for 18%).
     * @param  int  $term  Number of installments.
     */
    public function quote(
        float $principal,
        float $annualRate,
        int $term,
        InterestMethod $method,
        RepaymentFrequency $frequency,
        CarbonInterface $firstDueDate,
        float $processingFeePercent = 0,
    ): LoanQuote {
        if ($principal <= 0 || $term < 1 || $annualRate < 0) {
            throw new InvalidArgumentException('Principal and term must be positive and the rate cannot be negative.');
        }

        $periodicRate = $annualRate / 100 / $frequency->periodsPerYear();

        $lines = match ($method) {
            InterestMethod::Flat => $this->flatSchedule($principal, $periodicRate, $term),
            InterestMethod::ReducingBalance => $this->reducingSchedule($principal, $periodicRate, $term),
        };

        $schedule = collect($lines)->map(fn (array $line, int $index) => new ScheduleLine(
            number: $index + 1,
            dueDate: $frequency->dueDateFor($firstDueDate, $index + 1),
            principal: $line['principal'],
            interest: $line['interest'],
            balanceAfter: $line['balance'],
        ));

        return new LoanQuote(
            principal: round($principal, 2),
            totalInterest: round($schedule->sum('interest'), 2),
            processingFee: round($principal * $processingFeePercent / 100, 2),
            schedule: $schedule,
        );
    }

    /**
     * Interest is charged on the original principal for every period.
     *
     * @return list<array{principal: float, interest: float, balance: float}>
     */
    private function flatSchedule(float $principal, float $periodicRate, int $term): array
    {
        $totalInterest = round($principal * $periodicRate * $term, 2);
        $principalPart = round($principal / $term, 2);
        $interestPart = round($totalInterest / $term, 2);

        $lines = [];
        $balance = $principal;
        $interestAllocated = 0.0;

        for ($i = 1; $i <= $term; $i++) {
            $isLast = $i === $term;
            $principalPaid = $isLast ? round($balance, 2) : $principalPart;
            $interest = $isLast ? round($totalInterest - $interestAllocated, 2) : $interestPart;

            $balance = round($balance - $principalPaid, 2);
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
    private function reducingSchedule(float $principal, float $periodicRate, int $term): array
    {
        $installment = $periodicRate > 0
            ? round($principal * $periodicRate / (1 - (1 + $periodicRate) ** -$term), 2)
            : round($principal / $term, 2);

        $lines = [];
        $balance = $principal;

        for ($i = 1; $i <= $term; $i++) {
            $interest = round($balance * $periodicRate, 2);
            $principalPaid = $i === $term ? round($balance, 2) : round($installment - $interest, 2);
            $balance = round($balance - $principalPaid, 2);

            $lines[] = ['principal' => $principalPaid, 'interest' => $interest, 'balance' => max(0, $balance)];
        }

        return $lines;
    }
}
