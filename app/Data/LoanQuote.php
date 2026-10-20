<?php

namespace App\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final readonly class LoanQuote
{
    /**
     * @param  Collection<int, ScheduleLine>  $schedule
     */
    public function __construct(
        public float $principal,
        public float $totalInterest,
        public float $processingFee,
        public Collection $schedule,
    ) {}

    public function totalPayable(): float
    {
        return round($this->principal + $this->totalInterest, 2);
    }

    public function installmentAmount(): float
    {
        return $this->schedule->first()?->total() ?? 0.0;
    }

    public function maturityDate(): ?CarbonInterface
    {
        return $this->schedule->last()?->dueDate;
    }
}
