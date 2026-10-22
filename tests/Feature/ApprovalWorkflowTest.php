<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_return_an_application_for_correction(): void
    {
        $manager = User::factory()->manager()->create();
        $loan = Loan::factory()->create();

        $this->actingAs($manager)->post(route('loans.return', $loan), ['return_reason' => 'Missing income proof'])
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame(LoanStatus::Returned, $loan->status);
        $this->assertSame('Missing income proof', $loan->return_reason);
        $this->assertSame($manager->id, $loan->returned_by);

        $this->actingAs($manager)->get(route('loans.show', $loan))->assertOk()->assertSee('Missing income proof');
    }

    public function test_a_reason_is_required_to_return_an_application(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.return', $loan), ['return_reason' => ''])
            ->assertSessionHasErrors('return_reason');

        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);
    }

    public function test_loan_officers_cannot_return_applications(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('loans.return', $loan), ['return_reason' => 'x'])->assertForbidden();
    }

    public function test_officer_corrects_and_resubmits_a_returned_application(): void
    {
        $officer = User::factory()->create();
        $loan = Loan::factory()->create(['status' => LoanStatus::Returned, 'return_reason' => 'Amount too high']);

        $this->actingAs($officer)->put(route('loans.update', $loan), [
            'loan_product_id' => $loan->loan_product_id,
            'principal_amount' => 800,
            'term' => 12,
            'application_date' => today()->toDateString(),
        ])->assertRedirect(route('loans.show', $loan));

        $this->assertSame('800.00', $loan->fresh()->principal_amount);
        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);

        $this->actingAs($officer)->post(route('loans.resubmit', $loan))->assertSessionHas('success');
        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);
    }

    public function test_returned_applications_cannot_be_approved_until_resubmitted(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Returned]);

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.approve', $loan))->assertSessionHasErrors('status');
        $this->assertSame(LoanStatus::Returned, $loan->fresh()->status);
    }

    public function test_a_returned_application_still_counts_as_the_borrowers_open_loan(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Returned]);

        $this->assertTrue($loan->borrower->hasOpenLoan());
    }

    public function test_managers_cannot_approve_above_their_limit_but_admins_can(): void
    {
        Setting::setMany(['approval_limit_manager_usd' => '1000']);
        $loan = Loan::factory()->create(['principal_amount' => 1200]);

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.approve', $loan))->assertSessionHasErrors('status');
        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);

        $this->actingAs(User::factory()->admin()->create())->post(route('loans.approve', $loan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }

    public function test_managers_can_approve_within_their_limit(): void
    {
        Setting::setMany(['approval_limit_manager_usd' => '1200']);
        $loan = Loan::factory()->create(['principal_amount' => 1200]);

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.approve', $loan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }

    public function test_approval_limits_are_set_per_currency(): void
    {
        Setting::setMany(['approval_limit_manager_usd' => '1000', 'approval_limit_manager_khr' => '8000000']);
        $manager = User::factory()->manager()->create();
        $rielLoan = Loan::factory()->for(LoanProduct::factory()->khr()->create(), 'product')->create(['principal_amount' => 4000000]);

        $this->actingAs($manager)->post(route('loans.approve', $rielLoan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $rielLoan->fresh()->status);
    }

    public function test_maker_checker_stops_staff_approving_applications_they_prepared(): void
    {
        Setting::setMany(['require_maker_checker' => '1']);
        $maker = User::factory()->manager()->create();
        $officerLoan = Loan::factory()->create(['loan_officer_id' => $maker->id]);
        $createdLoan = Loan::factory()->create(['created_by' => $maker->id]);

        $this->actingAs($maker)->post(route('loans.approve', $officerLoan))->assertSessionHasErrors('status');
        $this->actingAs($maker)->post(route('loans.approve', $createdLoan))->assertSessionHasErrors('status');
        $this->assertSame(LoanStatus::Pending, $officerLoan->fresh()->status);

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.approve', $officerLoan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $officerLoan->fresh()->status);
    }

    public function test_makers_may_approve_their_own_applications_when_maker_checker_is_off(): void
    {
        $maker = User::factory()->manager()->create();
        $loan = Loan::factory()->create(['loan_officer_id' => $maker->id]);

        $this->actingAs($maker)->post(route('loans.approve', $loan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }

    public function test_maker_checker_can_be_switched_on_in_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('settings.update'), [
            'company_name' => 'Acme', 'currency_code' => 'USD', 'require_maker_checker' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Loan::makerCheckerEnabled());
    }

    public function test_loan_page_locks_the_approve_button_above_the_limit(): void
    {
        Setting::setMany(['approval_limit_manager_usd' => '500']);
        $loan = Loan::factory()->create(['principal_amount' => 1200]);

        $this->actingAs(User::factory()->manager()->create())->get(route('loans.show', $loan))
            ->assertOk()
            ->assertSee('Above your approval limit of $500.00');
    }
}
