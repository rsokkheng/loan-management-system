<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepaymentService
{
    public function __construct(private LedgerService $ledger) {}

    /**
     * Record a repayment and allocate it to the oldest installments first,
     * settling penalty, then interest, then principal on each.
     */
    public function record(
        Loan $loan,
        float $amount,
        CarbonInterface $paymentDate,
        PaymentMethod $method,
        User $receiver,
        ?string $transactionReference = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($loan, $amount, $paymentDate, $method, $receiver, $transactionReference, $notes) {
            /** @var Loan $loan */
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== LoanStatus::Active) {
                throw ValidationException::withMessages(['amount' => __('Payments can only be recorded for active loans.')]);
            }

            $amount = round($amount, 2);
            $outstanding = $loan->outstanding_balance;

            if ($amount <= 0 || $amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => __('The amount must be between 0.01 and the outstanding balance of :balance.', ['balance' => number_format($outstanding, 2)]),
                ]);
            }

            $allocation = ['penalty' => 0.0, 'interest' => 0.0, 'principal' => 0.0];
            $remaining = $amount;

            foreach ($loan->unpaidInstallments()->get() as $installment) {
                if ($remaining <= 0) {
                    break;
                }

                $remaining = $this->allocateTo($installment, $remaining, $allocation, $paymentDate);
            }

            $payment = $loan->payments()->create([
                'branch_id' => $loan->branch_id,
                'amount' => $amount,
                'penalty_amount' => round($allocation['penalty'], 2),
                'interest_amount' => round($allocation['interest'], 2),
                'principal_amount' => round($allocation['principal'], 2),
                'payment_date' => $paymentDate,
                'method' => $method,
                'transaction_reference' => $transactionReference,
                'notes' => $notes,
                'received_by' => $receiver->id,
            ]);

            $loan->amount_paid = round((float) $loan->amount_paid + $amount, 2);

            if (! $loan->unpaidInstallments()->exists()) {
                $loan->status = LoanStatus::Closed;
                $loan->closed_at = now();
            }

            $loan->save();

            $payment->setRelation('loan', $loan)->setRelation('receiver', $receiver);
            $this->ledger->recordRepayment($payment);

            ActivityLog::record('payment', "Payment {$payment->receipt_number} of ".number_format($amount, 2)." received for loan {$loan->reference}", $payment);

            return $payment;
        });
    }

    /**
     * @param  array{penalty: float, interest: float, principal: float}  $allocation
     */
    private function allocateTo(LoanInstallment $installment, float $remaining, array &$allocation, CarbonInterface $paymentDate): float
    {
        foreach (['penalty', 'interest', 'principal'] as $component) {
            $due = round((float) $installment->{"{$component}_due"} - (float) $installment->{"{$component}_paid"}, 2);
            $applied = round(min($due, $remaining), 2);

            if ($applied <= 0) {
                continue;
            }

            $installment->{"{$component}_paid"} = round((float) $installment->{"{$component}_paid"} + $applied, 2);
            $allocation[$component] += $applied;
            $remaining = round($remaining - $applied, 2);
        }

        if ($installment->balance <= 0) {
            $installment->status = InstallmentStatus::Paid;
            $installment->paid_at = $paymentDate;
        } elseif ($installment->total_paid > 0) {
            $installment->status = InstallmentStatus::Partial;
        }

        $installment->save();

        return $remaining;
    }
}
