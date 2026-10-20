<?php

namespace App\Data;

use Carbon\CarbonInterface;

final readonly class ScheduleLine
{
    public function __construct(
        public int $number,
        public CarbonInterface $dueDate,
        public float $principal,
        public float $interest,
        public float $balanceAfter,
    ) {}

    public function total(): float
    {
        return round($this->principal + $this->interest, 2);
    }
}
