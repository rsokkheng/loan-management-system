<?php

namespace Tests\Feature;

use App\Enums\JournalType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\LoanService;
use App\Services\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-15');
        Account::installDefaults();
        $this->branch = Branch::factory()->create();
        $this->manager = User::factory()->manager()->for($this->branch)->create();
    }

    /**
     * 1,200 flat loan at 24% for 12 months with a 2% (24.00) processing fee, disbursed in cash.
     */
    private function disbursedLoan(PaymentMethod $method = PaymentMethod::Cash): Loan
    {
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);
        $loan = Loan::factory()->for($product, 'product')->create([
            'branch_id' => $this->branch->id,
            'interest_rate' => 24,
            'principal_amount' => 1200,
            'processing_fee' => 24,
            'term' => 12,
            'status' => LoanStatus::Approved,
        ]);

        return app(LoanService::class)->disburse($loan, $this->manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), $method);
    }

    /**
     * @return array<string, array{debit: float, credit: float}>
     */
    private function linesOf(JournalEntry $entry): array
    {
        return $entry->lines()->with('account')->get()
            ->mapWithKeys(fn ($line) => [$line->account->system_key ?? $line->account->code => ['debit' => (float) $line->debit, 'credit' => (float) $line->credit]])
            ->all();
    }

    public function test_disbursement_posts_portfolio_cash_and_fee_income(): void
    {
        $loan = $this->disbursedLoan(PaymentMethod::BankTransfer);

        $entry = $loan->journalEntries()->sole();
        $this->assertSame(JournalType::Disbursement, $entry->type);
        $this->assertSame($this->branch->id, $entry->branch_id);
        $this->assertSame([
            'loan_portfolio' => ['debit' => 1200.0, 'credit' => 0.0],
            'bank' => ['debit' => 0.0, 'credit' => 1176.0],
            'fee_income' => ['debit' => 0.0, 'credit' => 24.0],
        ], $this->linesOf($entry));
    }

    public function test_repayment_splits_cash_into_principal_interest_and_penalty(): void
    {
        $loan = $this->disbursedLoan();
        $loan->installments()->first()->update(['penalty_due' => 6.20]);
        $loan->increment('total_penalty', 6.20);

        $payment = app(RepaymentService::class)->record($loan, 130.20, today(), PaymentMethod::MobileMoney, $this->manager);

        $this->assertSame([
            'mobile_money' => ['debit' => 130.2, 'credit' => 0.0],
            'loan_portfolio' => ['debit' => 0.0, 'credit' => 100.0],
            'interest_income' => ['debit' => 0.0, 'credit' => 24.0],
            'penalty_income' => ['debit' => 0.0, 'credit' => 6.2],
        ], $this->linesOf($payment->journalEntries()->sole()));
    }

    public function test_write_off_expenses_the_unpaid_principal(): void
    {
        $loan = $this->disbursedLoan();
        app(RepaymentService::class)->record($loan, 124, today(), PaymentMethod::Cash, $this->manager);

        app(LoanService::class)->writeOff($loan->fresh(), 'Borrower absconded');

        $entry = $loan->journalEntries()->where('type', JournalType::WriteOff)->sole();
        $this->assertSame([
            'loan_loss' => ['debit' => 1100.0, 'credit' => 0.0],
            'loan_portfolio' => ['debit' => 0.0, 'credit' => 1100.0],
        ], $this->linesOf($entry));
    }

    public function test_loans_receivable_matches_the_outstanding_principal(): void
    {
        $loan = $this->disbursedLoan();
        app(RepaymentService::class)->record($loan, 250, today(), PaymentMethod::Cash, $this->manager);

        $sheet = app(AccountingReportService::class)->balanceSheet(today());
        $portfolio = $sheet['assets']->firstWhere('system_key', 'loan_portfolio');

        $this->assertSame(1200 - (float) $loan->payments()->sum('principal_amount'), $portfolio->balance);
        $this->assertSame($sheet['total_assets'], round($sheet['total_liabilities'] + $sheet['total_equity'], 2));
    }

    public function test_manual_journal_entries_must_balance(): void
    {
        $cash = Account::system('cash');
        $capital = Account::system('capital');

        $this->actingAs($this->manager)->post(route('accounting.journal.store'), [
            'entry_date' => today()->toDateString(),
            'description' => 'Capital',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1000],
                ['account_id' => $capital->id, 'credit' => 900],
            ],
        ])->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('journal_entries', 0);

        $this->actingAs($this->manager)->post(route('accounting.journal.store'), [
            'entry_date' => today()->toDateString(),
            'description' => 'Capital',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 1000],
                ['account_id' => $capital->id, 'credit' => 1000],
                ['account_id' => '', 'debit' => '', 'credit' => ''],
            ],
        ])->assertRedirect();

        $entry = JournalEntry::sole();
        $this->assertSame($this->branch->id, $entry->branch_id);
        $this->assertCount(2, $entry->lines);
    }

    public function test_manual_entries_can_be_reversed_once(): void
    {
        $this->actingAs($this->manager)->post(route('accounting.transactions.store'), [
            'type' => 'expense',
            'entry_date' => today()->toDateString(),
            'account_id' => Account::query()->where('code', '5010')->value('id'),
            'cash_account_id' => Account::system('cash')->id,
            'amount' => 300,
            'description' => 'Office rent',
        ])->assertRedirect();

        $expense = JournalEntry::sole();
        $this->assertSame(JournalType::Expense, $expense->type);

        $this->actingAs($this->manager)->post(route('accounting.journal.reverse', $expense), ['reason' => 'Duplicate'])->assertRedirect();
        $this->actingAs($this->manager)->post(route('accounting.journal.reverse', $expense), ['reason' => 'Again'])->assertSessionHasErrors('entry');

        $reversal = JournalEntry::query()->where('type', JournalType::Reversal)->sole();
        $this->assertSame($expense->id, $reversal->reversal_of_id);
        $this->assertSame(0.0, app(AccountingReportService::class)->incomeStatement(today()->startOfYear(), today())['total_expenses']);
    }

    public function test_automatic_loan_entries_cannot_be_reversed(): void
    {
        $entry = $this->disbursedLoan()->journalEntries()->sole();

        $this->actingAs($this->manager)->post(route('accounting.journal.reverse', $entry), ['reason' => 'Mistake'])->assertSessionHasErrors('entry');
    }

    public function test_expense_category_must_be_an_expense_account(): void
    {
        $this->actingAs($this->manager)->post(route('accounting.transactions.store'), [
            'type' => 'expense',
            'entry_date' => today()->toDateString(),
            'account_id' => Account::system('interest_income')->id,
            'cash_account_id' => Account::system('cash')->id,
            'amount' => 10,
            'description' => 'Wrong category',
        ])->assertSessionHasErrors('account_id');
    }

    public function test_system_accounts_cannot_be_deleted(): void
    {
        $this->actingAs($this->manager)->delete(route('accounting.accounts.destroy', Account::system('cash')))->assertSessionHas('error');

        $this->assertModelExists(Account::system('cash'));
    }

    public function test_accountants_have_access_but_loan_officers_do_not(): void
    {
        $this->actingAs(User::factory()->accountant()->create())->get(route('accounting.trial-balance'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('accounting.trial-balance'))->assertForbidden();
    }

    public function test_backfill_posts_missing_entries_once(): void
    {
        $loan = $this->disbursedLoan();
        app(RepaymentService::class)->record($loan, 124, today(), PaymentMethod::Cash, $this->manager);
        JournalEntry::query()->delete();

        $this->artisan('accounting:backfill')->expectsOutputToContain('Posted 2 journal entries')->assertSuccessful();
        $this->artisan('accounting:backfill')->expectsOutputToContain('Posted 0 journal entries')->assertSuccessful();

        $trialBalance = app(AccountingReportService::class)->trialBalance(today());
        $this->assertSame($trialBalance['debit'], $trialBalance['credit']);
    }

    public function test_reports_are_limited_to_the_users_branch(): void
    {
        $this->disbursedLoan();
        $otherManager = User::factory()->manager()->for(Branch::factory())->create();

        $this->actingAs($otherManager)->get(route('accounting.trial-balance'))
            ->assertOk()
            ->assertViewHas('debit', 0.0);

        $this->actingAs($this->manager)->get(route('accounting.trial-balance'))
            ->assertOk()
            ->assertViewHas('debit', 1200.0);
    }
}
