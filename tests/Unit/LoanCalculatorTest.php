<?php

namespace Tests\Unit;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Services\LoanCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LoanCalculatorTest extends TestCase
{
    public function test_flat_rate_charges_interest_on_the_original_principal(): void
    {
        $quote = (new LoanCalculator)->quote(1200, 24, 12, InterestMethod::Flat, RepaymentFrequency::Monthly, CarbonImmutable::parse('2026-01-15'));

        $this->assertSame(288.0, $quote->totalInterest);
        $this->assertSame(1488.0, $quote->totalPayable());
        $this->assertCount(12, $quote->schedule);
        $this->assertSame(124.0, $quote->installmentAmount());
        $this->assertSame(0.0, $quote->schedule->last()->balanceAfter);
    }

    public function test_reducing_balance_produces_equal_installments_that_repay_the_principal(): void
    {
        $quote = (new LoanCalculator)->quote(1000, 12, 12, InterestMethod::ReducingBalance, RepaymentFrequency::Monthly, CarbonImmutable::parse('2026-01-15'));

        $this->assertSame(10.0, $quote->schedule->first()->interest);
        $this->assertSame(88.85, $quote->installmentAmount());
        $this->assertEqualsWithDelta(1000.0, $quote->schedule->sum('principal'), 0.001);
        $this->assertEqualsWithDelta(66.2, $quote->totalInterest, 0.05);
        $this->assertSame(0.0, $quote->schedule->last()->balanceAfter);
    }

    public function test_rounding_difference_is_absorbed_by_the_last_installment(): void
    {
        $quote = (new LoanCalculator)->quote(1000, 0, 3, InterestMethod::ReducingBalance, RepaymentFrequency::Monthly, CarbonImmutable::parse('2026-01-15'));

        $this->assertSame([333.33, 333.33, 333.34], $quote->schedule->pluck('principal')->all());
        $this->assertSame(0.0, $quote->totalInterest);
    }

    public function test_processing_fee_is_a_percentage_of_the_principal(): void
    {
        $quote = (new LoanCalculator)->quote(2500, 18, 6, InterestMethod::Flat, RepaymentFrequency::Monthly, CarbonImmutable::parse('2026-01-15'), 1.5);

        $this->assertSame(37.5, $quote->processingFee);
    }

    public function test_monthly_due_dates_do_not_overflow_short_months(): void
    {
        $quote = (new LoanCalculator)->quote(300, 10, 3, InterestMethod::Flat, RepaymentFrequency::Monthly, CarbonImmutable::parse('2027-01-31'));

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], $quote->schedule->map(fn ($line) => $line->dueDate->toDateString())->all());
    }

    public function test_weekly_schedule_uses_weekly_periods(): void
    {
        $quote = (new LoanCalculator)->quote(520, 52, 2, InterestMethod::Flat, RepaymentFrequency::Weekly, CarbonImmutable::parse('2026-03-02'));

        $this->assertSame(['2026-03-02', '2026-03-09'], $quote->schedule->map(fn ($line) => $line->dueDate->toDateString())->all());
        $this->assertSame(10.4, $quote->totalInterest);
    }

    public function test_it_rejects_a_non_positive_principal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LoanCalculator)->quote(0, 10, 12, InterestMethod::Flat, RepaymentFrequency::Monthly, CarbonImmutable::now());
    }
}
