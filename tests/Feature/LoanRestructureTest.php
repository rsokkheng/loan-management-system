<?php

namespace Tests\Feature;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanService;
use App\Services\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanRestructureTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-05-01');
        $this->manager = User::factory()->manager()->create();
    }

    /**
     * A 1,200 flat-rate loan at 24% over 12 months: 12 installments of 100 principal + 24 interest from 2026-06-01.
     */
    private function activeLoan(): Loan
    {
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);
        $loan = Loan::factory()->for($product, 'product')->create([
            'interest_rate' => 24, 'principal_amount' => 1200, 'term' => 12, 'status' => LoanStatus::Approved,
        ]);

        return app(LoanService::class)->disburse($loan, $this->manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);
    }

    /**
     * Charge a 10.00 penalty on the first installment.
     */
    private function chargePenalty(Loan $loan): void
    {
        $loan->installments()->where('installment_number', 1)->update(['penalty_due' => 10]);
        $loan->forceFill(['total_penalty' => 10])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function restructure(array $overrides = []): array
    {
        return $overrides + [
            'term' => 10,
            'interest_rate' => 12,
            'first_repayment_date' => '2026-09-15',
            'reason' => 'Shop closed by flooding',
        ];
    }

    public function test_unpaid_principal_is_rescheduled_and_arrears_are_carried_without_capitalising(): void
    {
        $loan = $this->activeLoan();
        $this->chargePenalty($loan);
        $this->travelTo('2026-08-15');

        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $loan->refresh();
        $installments = $loan->installments()->get();

        // 1 arrears row + 10 new installments; the 12 untouched originals are gone.
        $this->assertCount(11, $installments);

        $arrears = $installments->first();
        $this->assertTrue($arrears->is_arrears);
        $this->assertSame('0.00', $arrears->principal_due);
        $this->assertSame('72.00', $arrears->interest_due);
        $this->assertSame('10.00', $arrears->penalty_due);
        $this->assertSame('2026-09-15', $arrears->due_date->toDateString());

        $new = $installments->where('is_arrears', false);
        $this->assertSame(1200.0, round($new->sum('principal_due'), 2));
        $this->assertSame(120.0, round($new->sum('interest_due'), 2));
        $this->assertSame('2027-06-15', $loan->maturity_date->toDateString());

        $this->assertSame('192.00', $loan->total_interest);
        $this->assertSame('1392.00', $loan->total_payable);
        $this->assertSame(1402.0, $loan->outstanding_balance);
        $this->assertSame(1, $loan->restructure_count);
        $this->assertFalse($loan->isOverdue());

        $restructure = $loan->restructures()->sole();
        $this->assertSame('1200.00', $restructure->principal_rescheduled);
        $this->assertSame(12, $restructure->previous_remaining_installments);
        $this->assertSame('24.0000', $restructure->previous_interest_rate);
    }

    public function test_partly_paid_installments_are_closed_at_the_amount_paid(): void
    {
        $loan = $this->activeLoan();
        app(RepaymentService::class)->record($loan, 50, Carbon::parse('2026-06-01'), PaymentMethod::Cash, $this->manager);
        $this->travelTo('2026-08-15');

        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure())->assertSessionHasNoErrors();

        $first = $loan->installments()->where('installment_number', 1)->sole();
        $this->assertSame(InstallmentStatus::Paid, $first->status);
        $this->assertSame('26.00', $first->principal_due);
        $this->assertSame('24.00', $first->interest_due);

        $restructure = $loan->restructures()->sole();
        $this->assertSame('1174.00', $restructure->principal_rescheduled);
        $this->assertSame('48.00', $restructure->interest_carried);
        $this->assertSame(1174.0 + 48.0 + 117.4, $loan->fresh()->outstanding_balance);
    }

    public function test_penalty_can_be_waived(): void
    {
        $loan = $this->activeLoan();
        $this->chargePenalty($loan);
        $this->travelTo('2026-08-15');

        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure(['waive_penalty' => '1']))->assertSessionHasNoErrors();

        $loan->refresh();
        $this->assertSame('0.00', $loan->total_penalty);
        $this->assertSame('10.00', $loan->restructures()->sole()->penalty_waived);
        $this->assertSame('0.00', $loan->installments()->where('is_arrears', true)->sole()->penalty_due);
    }

    public function test_settling_after_a_restructure_charges_arrears_in_full_and_accrues_from_the_restructure_date(): void
    {
        $loan = $this->activeLoan();
        $this->travelTo('2026-08-15');
        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure());

        $quote = app(RepaymentService::class)->settlementQuote($loan->fresh(), Carbon::parse('2026-09-01'));

        // 72 arrears + 12 × 17/31 days of the first new period.
        $this->assertSame(1200.0, $quote->principal);
        $this->assertSame(78.58, $quote->interest);
    }

    public function test_loan_page_shows_the_restructure_history(): void
    {
        $loan = $this->activeLoan();
        $this->travelTo('2026-08-15');
        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure());

        $this->actingAs($this->manager)->get(route('loans.show', $loan))
            ->assertOk()
            ->assertSee('Shop closed by flooding')
            ->assertSee('24% → 12%');
    }

    public function test_the_first_new_due_date_must_be_in_the_future(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure(['first_repayment_date' => '2026-05-01']))
            ->assertSessionHasErrors('first_repayment_date');

        $this->assertSame(0, $loan->restructures()->count());
    }

    public function test_only_active_loans_can_be_restructured(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Pending]);

        $this->actingAs($this->manager)->post(route('loans.restructure', $loan), $this->restructure())->assertSessionHasErrors('status');
    }

    public function test_loan_officers_cannot_restructure(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs(User::factory()->create())->post(route('loans.restructure', $loan), $this->restructure())->assertForbidden();
    }
}
