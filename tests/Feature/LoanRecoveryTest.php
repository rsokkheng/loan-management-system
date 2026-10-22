<?php

namespace Tests\Feature;

use App\Enums\JournalType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-05-01');
        $this->officer = User::factory()->create();
    }

    /**
     * A written-off 1,200 flat loan at 24%: 1,488 owed, nothing repaid.
     */
    private function writtenOffLoan(): Loan
    {
        $admin = User::factory()->admin()->create();
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);
        $loan = Loan::factory()->for($product, 'product')->create(['principal_amount' => 1200, 'term' => 12, 'status' => LoanStatus::Approved]);
        app(LoanService::class)->disburse($loan, $admin, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);

        return app(LoanService::class)->writeOff($loan, 'Borrower moved abroad');
    }

    /**
     * @return array<string, mixed>
     */
    private function recovery(float $amount): array
    {
        return ['amount' => $amount, 'payment_date' => '2026-05-01', 'method' => 'cash'];
    }

    public function test_recovery_on_a_written_off_loan_is_booked_as_recovered_bad_debt(): void
    {
        $loan = $this->writtenOffLoan();

        $this->actingAs($this->officer)->post(route('loans.recoveries.store', $loan), $this->recovery(300))
            ->assertRedirect(route('payments.show', Payment::sole()));

        $payment = Payment::sole();
        $this->assertTrue($payment->is_recovery);
        $this->assertSame('0.00', $payment->principal_amount);

        $loan->refresh();
        $this->assertSame(LoanStatus::WrittenOff, $loan->status);
        $this->assertSame(1188.0, $loan->outstanding_balance);

        $entry = JournalEntry::query()->where('type', JournalType::Recovery)->sole();
        $this->assertEqualsWithDelta(300, (float) $entry->lines()->where('account_id', Account::system('cash')->id)->value('debit'), 0.001);
        $this->assertEqualsWithDelta(300, (float) $entry->lines()->where('account_id', Account::system('recovery_income')->id)->value('credit'), 0.001);

        $trialBalance = app(AccountingReportService::class)->trialBalance(today());
        $this->assertSame($trialBalance['debit'], $trialBalance['credit']);
    }

    public function test_recovery_cannot_exceed_the_unrecovered_balance(): void
    {
        $loan = $this->writtenOffLoan();

        $this->actingAs($this->officer)->post(route('loans.recoveries.store', $loan), $this->recovery(1488.01))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::count());
    }

    public function test_recoveries_are_only_for_written_off_loans(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Active]);

        $this->actingAs($this->officer)->post(route('loans.recoveries.store', $loan), $this->recovery(50))
            ->assertSessionHasErrors('amount');
    }

    public function test_written_off_loan_page_offers_recovery(): void
    {
        $loan = $this->writtenOffLoan();

        $this->actingAs($this->officer)->get(route('loans.show', $loan))
            ->assertOk()
            ->assertSee('Record Recovery');
    }
}
