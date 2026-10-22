<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\LoanInstallment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PenaltyService
{
    /**
     * Charge a one-off late penalty on every installment that is past its grace period.
     *
     * @return int Number of installments penalised.
     */
    public function applyLatePenalties(): int
    {
        $count = 0;

        LoanInstallment::query()
            ->with('loan')
            ->overdue()
            ->whereNull('penalty_applied_at')
            ->whereHas('loan', fn (Builder $query) => $query
                ->where('status', LoanStatus::Active)
                ->where('late_penalty_percent', '>', 0))
            ->chunkById(200, function ($installments) use (&$count): void {
                foreach ($installments as $installment) {
                    if ($installment->days_overdue <= $installment->loan->grace_period_days) {
                        continue;
                    }

                    $overdueAmount = round(
                        (float) $installment->principal_due + (float) $installment->interest_due
                        - (float) $installment->principal_paid - (float) $installment->interest_paid,
                        2,
                    );
                    $penalty = $installment->loan->currency->round($overdueAmount * (float) $installment->loan->late_penalty_percent / 100);

                    DB::transaction(function () use ($installment, $penalty): void {
                        $installment->forceFill([
                            'penalty_due' => round((float) $installment->penalty_due + $penalty, 2),
                            'penalty_applied_at' => now(),
                        ])->save();

                        $installment->loan->increment('total_penalty', $penalty);
                    });

                    $count++;
                }
            });

        return $count;
    }
}
