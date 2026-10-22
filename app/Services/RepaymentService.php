<?php

namespace App\Services;

use App\Data\SettlementQuote;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\Setting;
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
     * Record money collected on a written-off loan. The loan stays written off;
     * its outstanding balance shrinks and the cash is booked as recovered bad debt.
     */
    public function recordRecovery(
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

            if ($loan->status !== LoanStatus::WrittenOff) {
                throw ValidationException::withMessages(['amount' => __('Recoveries can only be recorded for written-off loans.')]);
            }

            $amount = $loan->currency->round($amount);
            $outstanding = $loan->outstanding_balance;

            if ($amount <= 0 || $amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => __('The amount must be more than zero and no more than the unrecovered balance of :balance.', ['balance' => $loan->currency->format($outstanding)]),
                ]);
            }

            $payment = $loan->payments()->create([
                'branch_id' => $loan->branch_id,
                'amount' => $amount,
                'is_recovery' => true,
                'payment_date' => $paymentDate,
                'method' => $method,
                'transaction_reference' => $transactionReference,
                'notes' => $notes,
                'received_by' => $receiver->id,
            ]);

            $loan->amount_paid = round((float) $loan->amount_paid + $amount, 2);
            $loan->save();

            $payment->setRelation('loan', $loan)->setRelation('receiver', $receiver);
            $this->ledger->recordRecovery($payment);

            ActivityLog::record('recovery', "Recovery {$payment->receipt_number} of ".number_format($amount, 2)." received on written-off loan {$loan->reference}", $payment);

            return $payment;
        });
    }

    /**
     * Quote the amount needed to pay a loan off early: all unpaid principal and penalties,
     * interest already due, interest accrued so far in the current period, and the
     * early settlement fee. Interest for periods not yet started is waived.
     */
    public function settlementQuote(Loan $loan, CarbonInterface $settlementDate): SettlementQuote
    {
        $installments = $loan->installments()->get();
        $settlementDate = $settlementDate->copy()->startOfDay();
        $periodStart = $loan->disbursed_at?->copy()->startOfDay() ?? $settlementDate;
        $principal = $penalty = $interest = $waived = 0.0;
        $installmentInterest = [];

        foreach ($installments as $installment) {
            $periodEnd = $installment->due_date->copy()->startOfDay();

            if ($installment->status !== InstallmentStatus::Paid) {
                $principal += (float) $installment->principal_due - (float) $installment->principal_paid;
                $penalty += (float) $installment->penalty_due - (float) $installment->penalty_paid;

                $interestDue = (float) $installment->interest_due;
                $interestPaid = (float) $installment->interest_paid;
                $currentPeriodStart = $loan->restructured_at && $periodEnd->gt($loan->restructured_at)
                    ? $periodStart->max($loan->restructured_at->copy()->startOfDay())
                    : $periodStart;
                $earned = match (true) {
                    $installment->is_arrears, $periodEnd->lte($settlementDate) => $interestDue,
                    $currentPeriodStart->gte($settlementDate) => 0.0,
                    default => $loan->currency->round($interestDue * $currentPeriodStart->diffInDays($settlementDate) / max(1, $currentPeriodStart->diffInDays($periodEnd))),
                };
                $owed = round(max(0, $earned - $interestPaid), 2);

                $installmentInterest[$installment->id] = [
                    'interest' => $owed,
                    'waived' => round(max(0, $interestDue - $interestPaid - $owed), 2),
                ];
                $interest += $owed;
                $waived += $installmentInterest[$installment->id]['waived'];
            }

            if (! $installment->is_arrears) {
                $periodStart = $periodEnd;
            }
        }

        $principal = round($principal, 2);
        $feePercent = (float) Setting::get('early_settlement_fee_percent', '0');
        $hasFutureInterest = $waived > 0;

        return new SettlementQuote(
            date: $settlementDate,
            principal: $principal,
            interest: round($interest, 2),
            penalty: round($penalty, 2),
            fee: $hasFutureInterest ? $loan->currency->round($principal * $feePercent / 100) : 0.0,
            feePercent: $hasFutureInterest ? $feePercent : 0.0,
            interestWaived: round($waived, 2),
            installmentInterest: $installmentInterest,
        );
    }

    /**
     * Close a loan early by collecting its settlement quote in a single payment.
     */
    public function settle(
        Loan $loan,
        CarbonInterface $settlementDate,
        PaymentMethod $method,
        User $receiver,
        ?string $transactionReference = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($loan, $settlementDate, $method, $receiver, $transactionReference, $notes) {
            /** @var Loan $loan */
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->status !== LoanStatus::Active) {
                throw ValidationException::withMessages(['payment_date' => __('Only active loans can be settled.')]);
            }

            if ($loan->disbursed_at && $settlementDate->lt($loan->disbursed_at)) {
                throw ValidationException::withMessages(['payment_date' => __('The settlement date cannot be before the disbursement date.')]);
            }

            $quote = $this->settlementQuote($loan, $settlementDate);

            foreach ($loan->unpaidInstallments()->get() as $installment) {
                $owed = $quote->installmentInterest[$installment->id] ?? ['interest' => 0.0, 'waived' => 0.0];

                $installment->interest_paid = round((float) $installment->interest_paid + $owed['interest'], 2);
                $installment->interest_due = round((float) $installment->interest_due - $owed['waived'], 2);
                $installment->principal_paid = $installment->principal_due;
                $installment->penalty_paid = $installment->penalty_due;
                $installment->status = InstallmentStatus::Paid;
                $installment->paid_at = $settlementDate;
                $installment->save();
            }

            $payment = $loan->payments()->create([
                'branch_id' => $loan->branch_id,
                'amount' => $quote->total(),
                'penalty_amount' => $quote->penalty,
                'interest_amount' => $quote->interest,
                'principal_amount' => $quote->principal,
                'is_settlement' => true,
                'settlement_fee' => $quote->fee,
                'interest_waived' => $quote->interestWaived,
                'payment_date' => $settlementDate,
                'method' => $method,
                'transaction_reference' => $transactionReference,
                'notes' => $notes,
                'received_by' => $receiver->id,
            ]);

            $loan->total_interest = round((float) $loan->total_interest - $quote->interestWaived, 2);
            $loan->total_payable = round((float) $loan->total_payable - $quote->interestWaived, 2);
            $loan->amount_paid = round((float) $loan->amount_paid + $quote->principal + $quote->interest + $quote->penalty, 2);
            $loan->status = LoanStatus::Closed;
            $loan->closed_at = now();
            $loan->save();

            $payment->setRelation('loan', $loan)->setRelation('receiver', $receiver);
            $this->ledger->recordRepayment($payment);

            ActivityLog::record('settled', "Loan {$loan->reference} settled early with payment {$payment->receipt_number} of ".number_format($quote->total(), 2), $payment, [
                'interest_waived' => $quote->interestWaived,
                'settlement_fee' => $quote->fee,
            ]);

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
