<?php

namespace Tests\Feature;

use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_loan_officers_cannot_approve_or_disburse(): void
    {
        $officer = User::factory()->create();
        $loan = Loan::factory()->create();

        $this->actingAs($officer)->post(route('loans.approve', $loan))->assertForbidden();
        $this->actingAs($officer)->post(route('loans.disburse', $loan), [])->assertForbidden();

        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);
    }

    public function test_managers_can_approve_pending_loans(): void
    {
        $manager = User::factory()->manager()->create();
        $loan = Loan::factory()->create();

        $this->actingAs($manager)->post(route('loans.approve', $loan))->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame(LoanStatus::Approved, $loan->status);
        $this->assertSame($manager->id, $loan->approved_by);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $manager = User::factory()->manager()->create();
        $loan = Loan::factory()->create();

        $this->actingAs($manager)->post(route('loans.reject', $loan))->assertSessionHasErrors('rejection_reason');

        $this->actingAs($manager)->post(route('loans.reject', $loan), ['rejection_reason' => 'Low income'])->assertSessionHas('success');
        $this->assertSame(LoanStatus::Rejected, $loan->fresh()->status);
        $this->assertSame('Low income', $loan->fresh()->rejection_reason);
    }

    public function test_pending_loans_cannot_be_disbursed_before_approval(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.disburse', $loan), [
            'disbursed_at' => today()->toDateString(),
            'first_repayment_date' => today()->addMonth()->toDateString(),
            'disbursement_method' => 'cash',
        ])->assertSessionHasErrors('status');

        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);
    }

    public function test_disbursement_generates_the_repayment_schedule(): void
    {
        $this->travelTo('2026-03-10');
        $manager = User::factory()->manager()->create();
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);
        $loan = Loan::factory()->for($product, 'product')->create([
            'interest_rate' => 24,
            'principal_amount' => 1200,
            'term' => 12,
            'status' => LoanStatus::Approved,
        ]);

        $this->actingAs($manager)->post(route('loans.disburse', $loan), [
            'disbursed_at' => '2026-03-10',
            'first_repayment_date' => '2026-04-10',
            'disbursement_method' => 'bank_transfer',
        ])->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertSame('2027-03-10', $loan->maturity_date->toDateString());
        $this->assertSame('1488.00', $loan->total_payable);
        $this->assertCount(12, $loan->installments);
        $this->assertSame('2026-04-10', $loan->installments->first()->due_date->toDateString());
        $this->assertTrue($loan->installments->every(fn ($i) => $i->status === InstallmentStatus::Pending));
        $this->assertDatabaseHas('activity_logs', ['action' => 'disbursed', 'subject_id' => $loan->id]);
    }

    public function test_first_repayment_must_be_after_disbursement(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Approved]);

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.disburse', $loan), [
            'disbursed_at' => today()->toDateString(),
            'first_repayment_date' => today()->toDateString(),
            'disbursement_method' => 'cash',
        ])->assertSessionHasErrors('first_repayment_date');
    }

    public function test_only_admins_can_write_off_loans(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Active]);

        $this->actingAs(User::factory()->manager()->create())
            ->post(route('loans.write-off', $loan), ['reason' => 'Borrower absconded'])
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('loans.write-off', $loan), ['reason' => 'Borrower absconded'])
            ->assertSessionHas('success');

        $this->assertSame(LoanStatus::WrittenOff, $loan->fresh()->status);
    }
}
