<?php

namespace Tests\Feature;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RepaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-06-01');
        $this->officer = User::factory()->create();
    }

    /**
     * A 1,200 flat-rate loan at 24% over 12 months: 12 installments of 100 principal + 24 interest.
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
    private function payment(float $amount): array
    {
        return ['amount' => $amount, 'payment_date' => '2026-06-01', 'method' => 'cash'];
    }

    public function test_payment_is_allocated_to_interest_before_principal(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)->post(route('payments.store', $loan), $this->payment(50));

        $payment = Payment::sole();
        $this->assertSame('24.00', $payment->interest_amount);
        $this->assertSame('26.00', $payment->principal_amount);
        $this->assertStringStartsWith('RCT-', $payment->receipt_number);

        $first = $loan->installments()->first();
        $this->assertSame(InstallmentStatus::Partial, $first->status);
        $this->assertSame('50.00', $loan->fresh()->amount_paid);
    }

    public function test_payment_spills_over_into_the_next_installment(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)
            ->post(route('payments.store', $loan), $this->payment(150))
            ->assertRedirect(route('payments.show', Payment::query()->first() ?? 1));

        [$first, $second] = $loan->installments()->take(2)->get();
        $this->assertSame(InstallmentStatus::Paid, $first->status);
        $this->assertSame('2026-06-01', $first->paid_at->toDateString());
        $this->assertSame(InstallmentStatus::Partial, $second->status);
        $this->assertSame('24.00', $second->interest_paid);
        $this->assertSame('2.00', $second->principal_paid);
    }

    public function test_penalties_are_settled_first(): void
    {
        $loan = $this->activeLoan();
        $loan->installments()->first()->update(['penalty_due' => 6.20]);
        $loan->increment('total_penalty', 6.20);

        $this->actingAs($this->officer)->post(route('payments.store', $loan), $this->payment(10));

        $payment = Payment::sole();
        $this->assertSame('6.20', $payment->penalty_amount);
        $this->assertSame('3.80', $payment->interest_amount);
        $this->assertSame('0.00', $payment->principal_amount);
    }

    public function test_paying_the_full_balance_closes_the_loan(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)->post(route('payments.store', $loan), $this->payment(1488));

        $loan->refresh();
        $this->assertSame(LoanStatus::Closed, $loan->status);
        $this->assertNotNull($loan->closed_at);
        $this->assertSame(0.0, $loan->outstanding_balance);
        $this->assertSame(0, $loan->unpaidInstallments()->count());
    }

    public function test_overpayment_is_rejected(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)
            ->post(route('payments.store', $loan), $this->payment(1488.01))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payments_cannot_be_recorded_on_inactive_loans(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs($this->officer)
            ->post(route('payments.store', $loan), $this->payment(100))
            ->assertSessionHasErrors('amount');
    }

    public function test_future_payment_dates_are_rejected(): void
    {
        $loan = $this->activeLoan();

        $this->actingAs($this->officer)
            ->post(route('payments.store', $loan), ['amount' => 50, 'payment_date' => '2026-06-02', 'method' => 'cash'])
            ->assertSessionHasErrors('payment_date');
    }

    public function test_receipt_page_shows_the_payment(): void
    {
        $loan = $this->activeLoan();
        $this->actingAs($this->officer)->post(route('payments.store', $loan), $this->payment(124));
        $payment = Payment::sole();

        $this->actingAs($this->officer)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertSee($loan->borrower->full_name);
    }
}
