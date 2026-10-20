<?php

namespace App\Services;

use App\Data\LoanQuote;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private LoanCalculator $calculator, private LedgerService $ledger) {}

    /**
     * Create a new loan application from a product's terms.
     *
     * @param  array{borrower_id: int, loan_product_id: int, principal_amount: float|string, term: int|string, loan_officer_id?: int|null, purpose?: string|null, notes?: string|null, application_date?: string|null}  $data
     */
    public function apply(array $data, User $creator): Loan
    {
        $product = LoanProduct::query()->findOrFail($data['loan_product_id']);
        $borrower = Borrower::query()->findOrFail($data['borrower_id']);

        $this->ensureBorrowerIsEligible($borrower);

        $loan = new Loan($this->termsFor($product, (float) $data['principal_amount'], (int) $data['term']) + [
            'branch_id' => $borrower->branch_id,
            'borrower_id' => $borrower->id,
            'loan_officer_id' => $data['loan_officer_id'] ?? $creator->id,
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'application_date' => $data['application_date'] ?? today(),
            'status' => LoanStatus::Pending,
            'created_by' => $creator->id,
        ]);
        $loan->save();

        ActivityLog::record('created', "Loan application {$loan->reference} created for {$borrower->full_name}", $loan);

        return $loan;
    }

    /**
     * Update a pending application, recalculating its terms.
     *
     * @param  array{loan_product_id: int, principal_amount: float|string, term: int|string, loan_officer_id?: int|null, purpose?: string|null, notes?: string|null, application_date?: string|null}  $data
     */
    public function update(Loan $loan, array $data): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Pending);

        $product = LoanProduct::query()->findOrFail($data['loan_product_id']);

        $loan->fill($this->termsFor($product, (float) $data['principal_amount'], (int) $data['term']) + [
            'loan_officer_id' => $data['loan_officer_id'] ?? $loan->loan_officer_id,
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'application_date' => $data['application_date'] ?? $loan->application_date,
        ])->save();

        ActivityLog::record('updated', "Loan application {$loan->reference} updated", $loan);

        return $loan;
    }

    public function approve(Loan $loan, User $approver): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Pending);

        $loan->forceFill([
            'status' => LoanStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ])->save();

        ActivityLog::record('approved', "Loan {$loan->reference} approved", $loan);

        return $loan;
    }

    public function reject(Loan $loan, User $rejecter, string $reason): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Pending, LoanStatus::Approved);

        $loan->forceFill([
            'status' => LoanStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejecter->id,
            'rejection_reason' => $reason,
        ])->save();

        ActivityLog::record('rejected', "Loan {$loan->reference} rejected", $loan, ['reason' => $reason]);

        return $loan;
    }

    /**
     * Release the funds and generate the repayment schedule.
     */
    public function disburse(
        Loan $loan,
        User $disburser,
        CarbonInterface $disbursedAt,
        CarbonInterface $firstRepaymentDate,
        PaymentMethod $method,
    ): Loan {
        $this->ensureStatus($loan, LoanStatus::Approved);

        if ($firstRepaymentDate->lte($disbursedAt)) {
            throw ValidationException::withMessages([
                'first_repayment_date' => __('The first repayment date must be after the disbursement date.'),
            ]);
        }

        return DB::transaction(function () use ($loan, $disburser, $disbursedAt, $firstRepaymentDate, $method) {
            $quote = $this->quoteFor($loan, $firstRepaymentDate);

            $loan->installments()->delete();
            $loan->installments()->createMany($quote->schedule->map(fn ($line) => [
                'installment_number' => $line->number,
                'due_date' => $line->dueDate,
                'principal_due' => $line->principal,
                'interest_due' => $line->interest,
                'balance_after' => $line->balanceAfter,
                'status' => InstallmentStatus::Pending,
            ])->all());

            $loan->forceFill([
                'status' => LoanStatus::Active,
                'disbursed_at' => $disbursedAt,
                'disbursed_by' => $disburser->id,
                'disbursement_method' => $method,
                'first_repayment_date' => $firstRepaymentDate,
                'maturity_date' => $quote->maturityDate(),
                'total_interest' => $quote->totalInterest,
                'total_payable' => $quote->totalPayable(),
            ])->save();

            $this->ledger->recordDisbursement($loan);

            ActivityLog::record('disbursed', "Loan {$loan->reference} disbursed", $loan, [
                'amount' => (float) $loan->principal_amount,
            ]);

            return $loan;
        });
    }

    public function writeOff(Loan $loan, string $reason): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Active);

        DB::transaction(function () use ($loan): void {
            $loan->forceFill(['status' => LoanStatus::WrittenOff, 'closed_at' => now()])->save();
            $this->ledger->recordWriteOff($loan, today());
        });

        ActivityLog::record('written_off', "Loan {$loan->reference} written off", $loan, [
            'reason' => $reason,
            'outstanding' => $loan->outstanding_balance,
        ]);

        return $loan;
    }

    /**
     * Calculate the schedule for a loan's stored terms.
     */
    public function quoteFor(Loan $loan, CarbonInterface $firstRepaymentDate): LoanQuote
    {
        return $this->calculator->quote(
            principal: (float) $loan->principal_amount,
            annualRate: (float) $loan->interest_rate,
            term: $loan->term,
            method: $loan->interest_method,
            frequency: $loan->repayment_frequency,
            firstDueDate: $firstRepaymentDate,
        );
    }

    /**
     * Snapshot the product's terms onto the loan so later product changes don't affect it.
     *
     * @return array<string, mixed>
     */
    private function termsFor(LoanProduct $product, float $principal, int $term): array
    {
        $quote = $this->calculator->quote(
            principal: $principal,
            annualRate: (float) $product->interest_rate,
            term: $term,
            method: $product->interest_method,
            frequency: $product->repayment_frequency,
            firstDueDate: today(),
            processingFeePercent: (float) $product->processing_fee_percent,
        );

        return [
            'loan_product_id' => $product->id,
            'principal_amount' => $principal,
            'term' => $term,
            'interest_rate' => $product->interest_rate,
            'interest_method' => $product->interest_method,
            'repayment_frequency' => $product->repayment_frequency,
            'processing_fee' => $quote->processingFee,
            'late_penalty_percent' => $product->late_penalty_percent,
            'grace_period_days' => $product->grace_period_days,
            'total_interest' => $quote->totalInterest,
            'total_payable' => $quote->totalPayable(),
        ];
    }

    private function ensureBorrowerIsEligible(Borrower $borrower): void
    {
        if ($borrower->is_blacklisted) {
            throw ValidationException::withMessages([
                'borrower_id' => __('This borrower is blacklisted and cannot apply for a loan.'),
            ]);
        }

        if ($borrower->hasOpenLoan()) {
            throw ValidationException::withMessages([
                'borrower_id' => __('This borrower already has a pending or active loan.'),
            ]);
        }
    }

    private function ensureStatus(Loan $loan, LoanStatus ...$allowed): void
    {
        if (! in_array($loan->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('This action is not allowed while the loan is :status.', ['status' => $loan->status->label()]),
            ]);
        }
    }
}
