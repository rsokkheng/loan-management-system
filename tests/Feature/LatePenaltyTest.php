<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LatePenaltyTest extends TestCase
{
    use RefreshDatabase;

    private function activeLoan(): Loan
    {
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24, 'late_penalty_percent' => 5, 'grace_period_days' => 3]);
        $loan = Loan::factory()->for($product, 'product')->create([
            'interest_rate' => 24,
            'principal_amount' => 1200,
            'term' => 12,
            'late_penalty_percent' => 5,
            'grace_period_days' => 3,
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

    public function test_no_penalty_is_charged_within_the_grace_period(): void
    {
        $this->travelTo('2026-05-01');
        $loan = $this->activeLoan();

        $this->travelTo('2026-06-04');
        $this->artisan('loans:apply-penalties')->assertSuccessful();

        $this->assertSame('0.00', $loan->fresh()->total_penalty);
    }

    public function test_penalty_is_charged_once_after_the_grace_period(): void
    {
        $this->travelTo('2026-05-01');
        $loan = $this->activeLoan();

        $this->travelTo('2026-06-05');
        $this->artisan('loans:apply-penalties')->assertSuccessful();
        $this->artisan('loans:apply-penalties')->assertSuccessful();

        // 5% of the 124.00 overdue installment.
        $this->assertSame('6.20', $loan->installments()->first()->penalty_due);
        $this->assertSame('6.20', $loan->fresh()->total_penalty);
        $this->assertSame(1494.2, $loan->fresh()->outstanding_balance);
    }

    public function test_overdue_loans_appear_in_the_overdue_filter(): void
    {
        $this->travelTo('2026-05-01');
        $loan = $this->activeLoan();
        $this->travelTo('2026-06-10');

        $this->actingAs(User::factory()->create())
            ->get(route('loans.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertSee($loan->reference);
    }
}
