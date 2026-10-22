<?php

namespace Tests\Feature;

use App\Enums\InstallmentStatus;
use App\Enums\JournalType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EarlySettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-16');
        $this->officer = User::factory()->create();
        Setting::setMany(['early_settlement_fee_percent' => '2']);
    }

    /**
     * A 1,200 flat-rate loan at 24% over 12 months: 12 installments of 100 principal + 24 interest,
     * the first due 2026-06-01 and still unpaid.
     */
    private function activeLoan(): Loan
    {
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);
        $loan = Loan::factory()->for($product, 'product')->create([
            'interest_rate' => 24,
            'principal_amount' => 1200,
            'term' => 12,
            'status' => LoanStatus::Approved,
        ]);

        return app(LoanService::class)->disburse(
            $loan,
            User::factory()->manager()->create(),
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-06-01'),
            PaymentMethod::Cash,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settlement(float $expectedTotal): array
    {
        return ['payment_date' => '2026-06-16', 'expected_total' => $expectedTotal, 'method' => 'cash'];
    }

    public function test_quote_charges_interest_to_date_and_waives_future_interest(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)->get(route('loans.settle', $loan))
            ->assertOk()
            ->assertViewHas('quote', function ($quote) {
                // Installment 1 is overdue (24 interest) and installment 2 is half way through its 30-day period (12).
                return $quote->principal === 1200.0
                    && $quote->interest === 36.0
                    && $quote->fee === 24.0
                    && $quote->interestWaived === 252.0
                    && $quote->total() === 1260.0;
            });
    }

    public function test_settling_closes_the_loan_and_marks_every_installment_paid(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)->post(route('loans.settle.store', $loan), $this->settlement(1260))
            ->assertRedirect(route('payments.show', Payment::sole()));

        $payment = Payment::sole();
        $this->assertTrue($payment->is_settlement);
        $this->assertSame('1260.00', $payment->amount);
        $this->assertSame('1200.00', $payment->principal_amount);
        $this->assertSame('36.00', $payment->interest_amount);
        $this->assertSame('24.00', $payment->settlement_fee);
        $this->assertSame('252.00', $payment->interest_waived);

        $loan->refresh();
        $this->assertSame(LoanStatus::Closed, $loan->status);
        $this->assertSame(0.0, $loan->outstanding_balance);
        $this->assertSame('36.00', $loan->total_interest);
        $this->assertSame(12, $loan->installments()->where('status', InstallmentStatus::Paid)->count());
        $this->assertSame('0.00', $loan->installments()->where('installment_number', 3)->value('interest_due'));
    }

    public function test_settlement_fee_is_posted_to_fee_income(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)->post(route('loans.settle.store', $loan), $this->settlement(1260));

        $entry = JournalEntry::query()->where('type', JournalType::Repayment)->sole();
        $this->assertEqualsWithDelta(1260, (float) $entry->lines()->where('account_id', Account::system('cash')->id)->value('debit'), 0.001);
        $this->assertEqualsWithDelta(24, (float) $entry->lines()->where('account_id', Account::system('fee_income')->id)->value('credit'), 0.001);
        $this->assertEqualsWithDelta(36, (float) $entry->lines()->where('account_id', Account::system('interest_income')->id)->value('credit'), 0.001);
    }

    public function test_a_stale_quote_is_not_accepted(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)->post(route('loans.settle.store', $loan), $this->settlement(1488))
            ->assertRedirect(route('loans.settle', ['loan' => $loan, 'date' => '2026-06-16']))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
        $this->assertSame(LoanStatus::Active, $loan->fresh()->status);
    }

    public function test_only_active_loans_can_be_settled(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Pending]);

        $this->actingAs($this->officer)->get(route('loans.settle', $loan))
            ->assertRedirect(route('loans.show', $loan));

        $this->actingAs($this->officer)->post(route('loans.settle.store', $loan), $this->settlement(0))
            ->assertSessionHasErrors('payment_date');

        $this->assertSame(0, Payment::count());
    }

    public function test_no_fee_or_waiver_after_the_final_installment_is_due(): void
    {
        $loan = $this->activeLoan();
        $this->travelTo('2027-06-01');

        $this->actingAs($this->officer)->get(route('loans.settle', $loan))
            ->assertViewHas('quote', fn ($quote) => $quote->fee === 0.0 && $quote->interestWaived === 0.0 && $quote->total() === 1488.0);
    }
}
