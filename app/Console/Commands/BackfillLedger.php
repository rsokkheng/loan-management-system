<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('accounting:backfill')]
#[Description('Post journal entries for disbursements, repayments and write-offs that have none yet')]
class BackfillLedger extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledger): int
    {
        Account::installDefaults();

        $created = $ledger->backfill();

        $this->components->info("Posted {$created} journal entr".($created === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
