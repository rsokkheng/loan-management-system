<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_officers_can_submit_an_application_with_terms_copied_from_the_product(): void
    {
        $officer = User::factory()->create();
        $borrower = Borrower::factory()->create();
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24, 'processing_fee_percent' => 2]);

        $response = $this->actingAs($officer)->post(route('loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 1200,
            'term' => 12,
            'application_date' => today()->toDateString(),
        ]);

        $loan = Loan::sole();
        $response->assertRedirect(route('loans.show', $loan));
        $this->assertSame(LoanStatus::Pending, $loan->status);
        $this->assertSame('24.0000', $loan->interest_rate);
        $this->assertSame('288.00', $loan->total_interest);
        $this->assertSame('1488.00', $loan->total_payable);
        $this->assertSame('24.00', $loan->processing_fee);
        $this->assertSame($officer->id, $loan->loan_officer_id);
        $this->assertStringStartsWith('LN-', $loan->reference);
    }

    public function test_amount_and_term_must_respect_product_limits(): void
    {
        $product = LoanProduct::factory()->create(['min_amount' => 500, 'max_amount' => 1000, 'min_term' => 3, 'max_term' => 6]);

        $this->actingAs(User::factory()->create())->post(route('loans.store'), [
            'borrower_id' => Borrower::factory()->create()->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 5000,
            'term' => 12,
            'application_date' => today()->toDateString(),
        ])->assertSessionHasErrors(['principal_amount', 'term']);

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_blacklisted_borrowers_cannot_apply(): void
    {
        $this->actingAs(User::factory()->create())->post(route('loans.store'), [
            'borrower_id' => Borrower::factory()->blacklisted()->create()->id,
            'loan_product_id' => LoanProduct::factory()->create()->id,
            'principal_amount' => 1000,
            'term' => 6,
            'application_date' => today()->toDateString(),
        ])->assertSessionHasErrors('borrower_id');
    }

    public function test_borrowers_with_an_open_loan_cannot_apply_again(): void
    {
        $existing = Loan::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('loans.store'), [
            'borrower_id' => $existing->borrower_id,
            'loan_product_id' => $existing->loan_product_id,
            'principal_amount' => 1000,
            'term' => 6,
            'application_date' => today()->toDateString(),
        ])->assertSessionHasErrors('borrower_id');

        $this->assertDatabaseCount('loans', 1);
    }

    public function test_inactive_products_cannot_be_used(): void
    {
        $this->actingAs(User::factory()->create())->post(route('loans.store'), [
            'borrower_id' => Borrower::factory()->create()->id,
            'loan_product_id' => LoanProduct::factory()->inactive()->create()->id,
            'principal_amount' => 1000,
            'term' => 6,
            'application_date' => today()->toDateString(),
        ])->assertSessionHasErrors('loan_product_id');
    }

    public function test_pending_applications_can_be_edited_and_terms_are_recalculated(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->create())->put(route('loans.update', $loan), [
            'loan_product_id' => $loan->loan_product_id,
            'principal_amount' => 2400,
            'term' => 6,
            'application_date' => today()->toDateString(),
        ])->assertRedirect(route('loans.show', $loan));

        $loan->refresh();
        $this->assertSame('2400.00', $loan->principal_amount);
        $this->assertSame(6, $loan->term);
        $this->assertSame('24.00', $loan->processing_fee);
    }

    public function test_only_pending_or_rejected_applications_can_be_deleted(): void
    {
        $manager = User::factory()->manager()->create();
        $loan = Loan::factory()->create(['status' => LoanStatus::Approved]);

        $this->actingAs($manager)->delete(route('loans.destroy', $loan))->assertSessionHas('error');
        $this->assertNotSoftDeleted($loan);

        $loan->forceFill(['status' => LoanStatus::Rejected])->save();
        $this->actingAs($manager)->delete(route('loans.destroy', $loan))->assertRedirect(route('loans.index'));
        $this->assertSoftDeleted($loan);
    }
}
