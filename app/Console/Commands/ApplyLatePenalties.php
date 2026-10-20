<?php

namespace App\Console\Commands;

use App\Services\PenaltyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loans:apply-penalties')]
#[Description('Charge late penalties on installments that are past their grace period')]
class ApplyLatePenalties extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PenaltyService $penalties): int
    {
        $count = $penalties->applyLatePenalties();

        $this->components->info("Late penalties applied to {$count} installment(s).");

        return self::SUCCESS;
    }
}
