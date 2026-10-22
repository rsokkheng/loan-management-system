<?php

namespace App\Data;

use Carbon\CarbonInterface;

/**
 * What a borrower must pay to close a loan in full on a given date.
 */
final readonly class SettlementQuote
{
    /**
     * @param  array<int, array{interest: float, waived: float}>  $installmentInterest  Interest owed and waived, keyed by installment id.
     */
    public function __construct(
        public CarbonInterface $date,
        public float $principal,
        public float $interest,
        public float $penalty,
        public float $fee,
        public float $feePercent,
        public float $interestWaived,
        public array $installmentInterest,
    ) {}

    public function total(): float
    {
        return round($this->principal + $this->interest + $this->penalty + $this->fee, 2);
    }
}
