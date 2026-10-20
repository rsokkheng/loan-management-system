<?php

namespace App\Services;

use App\Enums\JournalType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts balanced double-entry journals, both for the loan workflow and for manual bookkeeping.
 */
class LedgerService
{
    /**
     * Post a journal entry.
     *
     * @param  list<array{account: Account|int, debit?: float|int|string|null, credit?: float|int|string|null, memo?: string|null}>  $lines
     */
    public function post(
        JournalType $type,
        CarbonInterface $date,
        string $description,
        array $lines,
        ?int $branchId,
        ?Model $source = null,
        ?User $user = null,
        ?JournalEntry $reversalOf = null,
    ): JournalEntry {
        $lines = collect($lines)
            ->map(fn (array $line) => [
                'account_id' => $line['account'] instanceof Account ? $line['account']->id : (int) $line['account'],
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'memo' => $line['memo'] ?? null,
            ])
            ->reject(fn (array $line) => $line['debit'] == 0 && $line['credit'] == 0)
            ->values();

        $this->ensureBalanced($lines->all());

        return DB::transaction(function () use ($type, $date, $description, $lines, $branchId, $source, $user, $reversalOf) {
            $entry = JournalEntry::query()->create([
                'branch_id' => $branchId,
                'entry_date' => $date,
                'type' => $type,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reversal_of_id' => $reversalOf?->id,
                'created_by' => $user?->id ?? auth()->id(),
            ]);

            $entry->lines()->createMany($lines->all());

            return $entry;
        });
    }

    /**
     * Dr Loans Receivable / Cr Cash (net payout) and Cr Fee Income.
     */
    public function recordDisbursement(Loan $loan): JournalEntry
    {
        $loan->loadMissing('disburser');
        $principal = (float) $loan->principal_amount;
        $fee = min((float) $loan->processing_fee, $principal);

        return $this->post(
            JournalType::Disbursement,
            $loan->disbursed_at,
            __('Loan :reference disbursed', ['reference' => $loan->reference]),
            [
                ['account' => Account::system('loan_portfolio'), 'debit' => $principal],
                ['account' => $this->cashAccountFor($loan->disbursement_method), 'credit' => $principal - $fee],
                ['account' => Account::system('fee_income'), 'credit' => $fee, 'memo' => __('Processing fee')],
            ],
            $loan->branch_id,
            $loan,
            $loan->disburser,
        );
    }

    /**
     * Dr Cash / Cr Loans Receivable, Interest Income and Penalty Income.
     */
    public function recordRepayment(Payment $payment): JournalEntry
    {
        $payment->loadMissing(['loan', 'receiver']);

        return $this->post(
            JournalType::Repayment,
            $payment->payment_date,
            __('Repayment :receipt for loan :reference', ['receipt' => $payment->receipt_number, 'reference' => $payment->loan->reference]),
            [
                ['account' => $this->cashAccountFor($payment->method), 'debit' => $payment->amount],
                ['account' => Account::system('loan_portfolio'), 'credit' => $payment->principal_amount],
                ['account' => Account::system('interest_income'), 'credit' => $payment->interest_amount],
                ['account' => Account::system('penalty_income'), 'credit' => $payment->penalty_amount],
            ],
            $payment->branch_id,
            $payment,
            $payment->receiver,
        );
    }

    /**
     * Dr Loan Loss Expense / Cr Loans Receivable for the principal never repaid.
     */
    public function recordWriteOff(Loan $loan, CarbonInterface $date): ?JournalEntry
    {
        $principalRepaid = (float) $loan->payments()->sum('principal_amount');
        $unpaidPrincipal = round((float) $loan->principal_amount - $principalRepaid, 2);

        if ($unpaidPrincipal <= 0) {
            return null;
        }

        return $this->post(
            JournalType::WriteOff,
            $date,
            __('Loan :reference written off', ['reference' => $loan->reference]),
            [
                ['account' => Account::system('loan_loss'), 'debit' => $unpaidPrincipal],
                ['account' => Account::system('loan_portfolio'), 'credit' => $unpaidPrincipal],
            ],
            $loan->branch_id,
            $loan,
        );
    }

    /**
     * Post an equal and opposite entry, leaving the original untouched for the audit trail.
     */
    public function reverse(JournalEntry $entry, User $user, string $reason): JournalEntry
    {
        if (! $entry->canBeReversed()) {
            throw ValidationException::withMessages(['entry' => __('Automatic loan entries cannot be reversed manually.')]);
        }

        if ($entry->reversedBy()->exists()) {
            throw ValidationException::withMessages(['entry' => __('This entry has already been reversed.')]);
        }

        return $this->post(
            JournalType::Reversal,
            today(),
            __('Reversal of :reference: :reason', ['reference' => $entry->reference, 'reason' => $reason]),
            $entry->lines->map(fn ($line) => [
                'account' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => $line->memo,
            ])->all(),
            $entry->branch_id,
            $entry,
            $user,
            $entry,
        );
    }

    /**
     * Post journals for loan activity that has none yet (e.g. data created before accounting existed).
     *
     * @return int Number of entries created.
     */
    public function backfill(): int
    {
        $created = 0;

        Loan::query()->withoutGlobalScopes()->whereNotNull('disbursed_at')
            ->whereDoesntHave('journalEntries', fn ($query) => $query->withoutGlobalScopes()->where('type', JournalType::Disbursement))
            ->with('disburser')
            ->orderBy('id')
            ->each(function (Loan $loan) use (&$created): void {
                $this->recordDisbursement($loan);
                $created++;
            });

        Payment::query()->withoutGlobalScopes()
            ->whereDoesntHave('journalEntries', fn ($query) => $query->withoutGlobalScopes())
            ->with(['loan' => fn ($query) => $query->withoutGlobalScopes(), 'receiver'])
            ->orderBy('id')
            ->each(function (Payment $payment) use (&$created): void {
                $this->recordRepayment($payment);
                $created++;
            });

        Loan::query()->withoutGlobalScopes()->where('status', LoanStatus::WrittenOff)
            ->whereDoesntHave('journalEntries', fn ($query) => $query->withoutGlobalScopes()->where('type', JournalType::WriteOff))
            ->orderBy('id')
            ->each(function (Loan $loan) use (&$created): void {
                if ($this->recordWriteOff($loan, $loan->closed_at ?? today())) {
                    $created++;
                }
            });

        return $created;
    }

    public function cashAccountFor(?PaymentMethod $method): Account
    {
        return Account::system(match ($method) {
            PaymentMethod::BankTransfer, PaymentMethod::Cheque => 'bank',
            PaymentMethod::MobileMoney => 'mobile_money',
            default => 'cash',
        });
    }

    /**
     * @param  list<array{account_id: int, debit: float, credit: float, memo: ?string}>  $lines
     */
    private function ensureBalanced(array $lines): void
    {
        $debits = round(array_sum(array_column($lines, 'debit')), 2);
        $credits = round(array_sum(array_column($lines, 'credit')), 2);

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => __('A journal entry needs at least two lines.')]);
        }

        foreach ($lines as $line) {
            if ($line['debit'] < 0 || $line['credit'] < 0 || ($line['debit'] > 0 && $line['credit'] > 0)) {
                throw ValidationException::withMessages(['lines' => __('Each line must have either a debit or a credit amount, not both.')]);
            }
        }

        if ($debits !== $credits) {
            throw ValidationException::withMessages([
                'lines' => __('Debits (:debits) must equal credits (:credits).', ['debits' => number_format($debits, 2), 'credits' => number_format($credits, 2)]),
            ]);
        }
    }
}
