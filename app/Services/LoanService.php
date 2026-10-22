<?php

namespace App\Services;

use App\Data\LoanQuote;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\ScheduleType;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanProduct;
use App\Models\LoanRestructure;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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

        $loan = new Loan($this->termsFor($product, (float) $data['principal_amount'], (int) $data['term'], $data['plan'] ?? null, $data['application_date'] ?? null) + [
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
        $this->ensureStatus($loan, LoanStatus::Pending, LoanStatus::Returned);

        $product = LoanProduct::query()->findOrFail($data['loan_product_id']);

        $loan->fill($this->termsFor($product, (float) $data['principal_amount'], (int) $data['term'], $data['plan'] ?? null, $data['application_date'] ?? null) + [
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
        $this->ensureNotApprovingOwnApplication($loan, $approver);
        $this->ensureWithinApprovalLimit($loan, $approver);
        $this->ensureSecurityRequirementsMet($loan);

        $loan->forceFill([
            'status' => LoanStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ])->save();

        ActivityLog::record('approved', "Loan {$loan->reference} approved", $loan);

        return $loan;
    }

    /**
     * Send an application back to the loan officer to fix, without rejecting it.
     */
    public function returnForCorrection(Loan $loan, User $reviewer, string $reason): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Pending);

        $loan->forceFill([
            'status' => LoanStatus::Returned,
            'returned_at' => now(),
            'returned_by' => $reviewer->id,
            'return_reason' => $reason,
        ])->save();

        ActivityLog::record('returned', "Loan {$loan->reference} returned for correction", $loan, ['reason' => $reason]);

        return $loan;
    }

    /**
     * Put a corrected application back in the approval queue.
     */
    public function resubmit(Loan $loan): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Returned);

        $loan->forceFill(['status' => LoanStatus::Pending])->save();

        ActivityLog::record('resubmitted', "Loan {$loan->reference} resubmitted for approval", $loan);

        return $loan;
    }

    public function reject(Loan $loan, User $rejecter, string $reason): Loan
    {
        $this->ensureStatus($loan, LoanStatus::Pending, LoanStatus::Returned, LoanStatus::Approved);

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

        if ($loan->hasFlexibleSchedule()) {
            // Flexible loans keep the due dates agreed in the application.
            $firstRepaymentDate = Carbon::parse($loan->flexible_plan[0]['due_date']);
        }

        if ($firstRepaymentDate->lte($disbursedAt)) {
            throw ValidationException::withMessages([
                'first_repayment_date' => $loan->hasFlexibleSchedule()
                    ? __('The first due date in the flexible schedule must be after the disbursement date.')
                    : __('The first repayment date must be after the disbursement date.'),
            ]);
        }

        return DB::transaction(function () use ($loan, $disburser, $disbursedAt, $firstRepaymentDate, $method) {
            $quote = $this->quoteFor($loan, $firstRepaymentDate, $disbursedAt);

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

    /**
     * Reschedule an active loan's unpaid principal over a new term.
     *
     * Overdue interest and penalties are not capitalised (that would book unearned income);
     * they move into a separate "arrears" installment due with the first new installment,
     * unless the penalty is waived.
     */
    public function restructure(
        Loan $loan,
        User $user,
        int $newTerm,
        float $newInterestRate,
        CarbonInterface $firstRepaymentDate,
        bool $waivePenalty,
        string $reason,
    ): LoanRestructure {
        $this->ensureStatus($loan, LoanStatus::Active);

        if ($firstRepaymentDate->lte(today())) {
            throw ValidationException::withMessages([
                'first_repayment_date' => __('The first new repayment date must be in the future.'),
            ]);
        }

        return DB::transaction(function () use ($loan, $user, $newTerm, $newInterestRate, $firstRepaymentDate, $waivePenalty, $reason) {
            /** @var Loan $loan */
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $unpaid = $loan->unpaidInstallments()->get();
            $currency = $loan->currency;

            $principal = $currency->round($unpaid->sum(fn (LoanInstallment $i) => (float) $i->principal_due - (float) $i->principal_paid));
            $arrearsInterest = $currency->round($unpaid
                ->filter(fn (LoanInstallment $i) => $i->is_arrears || $i->due_date->lte(today()))
                ->sum(fn (LoanInstallment $i) => (float) $i->interest_due - (float) $i->interest_paid));
            $penalty = $currency->round($unpaid->sum(fn (LoanInstallment $i) => (float) $i->penalty_due - (float) $i->penalty_paid));

            if ($principal <= 0) {
                throw ValidationException::withMessages(['term' => __('There is no unpaid principal left to reschedule.')]);
            }

            // Close out the old schedule: drop untouched installments, settle partly-paid ones at what was paid.
            foreach ($unpaid as $installment) {
                if ($installment->total_paid <= 0) {
                    $installment->delete();

                    continue;
                }

                $installment->forceFill([
                    'principal_due' => $installment->principal_paid,
                    'interest_due' => $installment->interest_paid,
                    'penalty_due' => $installment->penalty_paid,
                    'status' => InstallmentStatus::Paid,
                    'paid_at' => $installment->paid_at ?? today(),
                ])->save();
            }

            $quote = $this->calculator->quote(
                principal: $principal,
                annualRate: $newInterestRate,
                term: $newTerm,
                method: $loan->interest_method,
                frequency: $loan->repayment_frequency,
                firstDueDate: $firstRepaymentDate,
                precision: $currency->precision(),
            );

            $nextNumber = (int) $loan->installments()->max('installment_number') + 1;
            $penaltyCarried = $waivePenalty ? 0.0 : $penalty;

            if ($arrearsInterest > 0 || $penaltyCarried > 0) {
                $loan->installments()->create([
                    'installment_number' => $nextNumber++,
                    'due_date' => $firstRepaymentDate,
                    'principal_due' => 0,
                    'interest_due' => $arrearsInterest,
                    'penalty_due' => $penaltyCarried,
                    'balance_after' => $principal,
                    'is_arrears' => true,
                    'status' => InstallmentStatus::Pending,
                ]);
            }

            $loan->installments()->createMany($quote->schedule->map(fn ($line) => [
                'installment_number' => $nextNumber + $line->number - 1,
                'due_date' => $line->dueDate,
                'principal_due' => $line->principal,
                'interest_due' => $line->interest,
                'balance_after' => $line->balanceAfter,
                'status' => InstallmentStatus::Pending,
            ])->all());

            $restructure = $loan->restructures()->create([
                'restructure_date' => today(),
                'principal_rescheduled' => $principal,
                'interest_carried' => $arrearsInterest,
                'penalty_carried' => $penaltyCarried,
                'penalty_waived' => $waivePenalty ? $penalty : 0,
                'previous_interest_rate' => $loan->interest_rate,
                'new_interest_rate' => $newInterestRate,
                'previous_remaining_installments' => $unpaid->where('is_arrears', false)->count(),
                'new_term' => $newTerm,
                'previous_maturity_date' => $loan->maturity_date,
                'new_maturity_date' => $quote->maturityDate(),
                'reason' => $reason,
                'created_by' => $user->id,
            ]);

            $installments = $loan->installments()->get();

            $loan->forceFill([
                'interest_rate' => $newInterestRate,
                'maturity_date' => $quote->maturityDate(),
                'total_interest' => $currency->round($installments->sum('interest_due')),
                'total_payable' => $currency->round((float) $loan->principal_amount + $installments->sum('interest_due')),
                'total_penalty' => $currency->round($installments->sum('penalty_due')),
                'restructured_at' => today(),
                'restructure_count' => $loan->restructure_count + 1,
            ])->save();

            ActivityLog::record('restructured', "Loan {$loan->reference} restructured over {$newTerm} installments", $loan, [
                'principal' => $principal,
                'interest_rate' => $newInterestRate,
                'penalty_waived' => $restructure->penalty_waived,
                'reason' => $reason,
            ]);

            return $restructure;
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
    public function quoteFor(Loan $loan, CarbonInterface $firstRepaymentDate, ?CarbonInterface $startDate = null): LoanQuote
    {
        if ($loan->hasFlexibleSchedule()) {
            return $this->calculator->flexibleQuote(
                principal: (float) $loan->principal_amount,
                annualRate: (float) $loan->interest_rate,
                method: $loan->interest_method,
                startDate: $startDate ?? $loan->disbursed_at ?? today(),
                plan: $loan->flexible_plan ?? [],
                precision: $loan->currency->precision(),
            );
        }

        return $this->calculator->quote(
            principal: (float) $loan->principal_amount,
            annualRate: (float) $loan->interest_rate,
            term: $loan->term,
            method: $loan->interest_method,
            frequency: $loan->repayment_frequency,
            firstDueDate: $firstRepaymentDate,
            precision: $loan->currency->precision(),
        );
    }

    /**
     * Snapshot the product's terms onto the loan so later product changes don't affect it.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  list<array{due_date: string, principal: float|string}>|null  $plan  Due dates and principal for flexible products.
     */
    private function termsFor(LoanProduct $product, float $principal, int $term, ?array $plan = null, ?string $applicationDate = null): array
    {
        $isFlexible = $product->schedule_type === ScheduleType::Flexible;

        $quote = $isFlexible
            ? $this->calculator->flexibleQuote(
                principal: $principal,
                annualRate: (float) $product->interest_rate,
                method: $product->interest_method,
                startDate: $applicationDate ? Carbon::parse($applicationDate) : today(),
                plan: $plan ?? [],
                processingFeePercent: (float) $product->processing_fee_percent,
                precision: $product->currency->precision(),
            )
            : $this->calculator->quote(
                principal: $principal,
                annualRate: (float) $product->interest_rate,
                term: $term,
                method: $product->interest_method,
                frequency: $product->repayment_frequency,
                firstDueDate: today(),
                processingFeePercent: (float) $product->processing_fee_percent,
                precision: $product->currency->precision(),
            );

        return [
            'schedule_type' => $product->schedule_type,
            'flexible_plan' => $isFlexible ? collect($plan)->map(fn (array $row) => [
                'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                'principal' => $product->currency->round($row['principal']),
            ])->values()->all() : null,
            'loan_product_id' => $product->id,
            'currency' => $product->currency,
            'principal_amount' => $principal,
            'term' => $isFlexible ? count($plan ?? []) : $term,
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

    /**
     * Maker-checker: the person who prepared an application must not approve it.
     */
    private function ensureNotApprovingOwnApplication(Loan $loan, User $approver): void
    {
        if (Loan::makerCheckerEnabled() && $loan->isMadeBy($approver)) {
            throw ValidationException::withMessages([
                'status' => __('You prepared this application, so another staff member must approve it.'),
            ]);
        }
    }

    private function ensureWithinApprovalLimit(Loan $loan, User $approver): void
    {
        if (! $approver->canApproveAmount((float) $loan->principal_amount, $loan->currency)) {
            throw ValidationException::withMessages([
                'status' => __('This loan exceeds your approval limit of :limit. It must be approved by an administrator.', [
                    'limit' => $loan->currency->format($approver->approvalLimit($loan->currency)),
                ]),
            ]);
        }
    }

    /**
     * Enforce the product's guarantor and collateral requirements before approval.
     */
    private function ensureSecurityRequirementsMet(Loan $loan): void
    {
        $loan->loadMissing(['product', 'guarantors', 'collaterals']);

        if ($loan->product->requires_guarantor && $loan->guarantors->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => __('This product requires at least one guarantor before approval.'),
            ]);
        }

        $requiredCoverage = (float) $loan->product->min_collateral_coverage;

        if ($requiredCoverage > 0 && $loan->collateralCoverage() < $requiredCoverage) {
            throw ValidationException::withMessages([
                'status' => __('Collateral covers :coverage% of the principal; this product requires at least :required%.', [
                    'coverage' => $loan->collateralCoverage(),
                    'required' => $requiredCoverage + 0,
                ]),
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
