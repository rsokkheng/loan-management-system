<?php

namespace Tests\Feature;

use App\Enums\CollateralStatus;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\Collateral;
use App\Models\FamilyMember;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LoanSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function guarantorData(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Sok Dara',
            'relationship' => 'sibling',
            'national_id' => '010203040',
            'phone' => '012345678',
            'monthly_income' => 600,
        ];
    }

    public function test_guarantors_can_be_added_to_a_pending_loan(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs($this->manager)->post(route('loans.guarantors.store', $loan), $this->guarantorData())
            ->assertSessionHas('success');

        $this->assertSame('Sok Dara', $loan->guarantors()->sole()->full_name);
        $this->actingAs($this->manager)->get(route('loans.show', $loan))->assertOk()->assertSee('Sok Dara');
    }

    public function test_the_borrower_cannot_guarantee_their_own_loan(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('loans.guarantors.store', $loan), $this->guarantorData(['national_id' => $loan->borrower->national_id]))
            ->assertSessionHasErrors('national_id');

        $this->assertSame(0, Guarantor::count());
    }

    public function test_guarantors_and_collateral_are_locked_after_disbursement(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Active]);
        $guarantor = Guarantor::factory()->for($loan)->create();

        $this->actingAs($this->manager)->post(route('loans.guarantors.store', $loan), $this->guarantorData())->assertSessionHas('error');
        $this->actingAs($this->manager)->delete(route('guarantors.destroy', $guarantor))->assertSessionHas('error');
        $this->actingAs($this->manager)->post(route('loans.collaterals.store', $loan), [
            'type' => 'motorbike', 'description' => 'Honda', 'estimated_value' => 900,
        ])->assertSessionHas('error');

        $this->assertModelExists($guarantor);
        $this->assertSame(0, Collateral::count());
    }

    public function test_collateral_is_recorded_with_its_title_document(): void
    {
        Storage::fake('local');
        $loan = Loan::factory()->create();

        $this->actingAs($this->manager)->post(route('loans.collaterals.store', $loan), [
            'type' => 'land_hard_title',
            'description' => 'Residential land 10x20m',
            'document_number' => 'KH-123456',
            'estimated_value' => 15000,
            'document' => UploadedFile::fake()->create('title.pdf', 200, 'application/pdf'),
        ])->assertSessionHas('success');

        $collateral = $loan->collaterals()->sole();
        $this->assertSame(CollateralStatus::Held, $collateral->status);
        Storage::disk('local')->assertExists($collateral->file_path);

        $this->actingAs($this->manager)->get(route('collaterals.document', $collateral))->assertDownload('title.pdf');
    }

    public function test_approval_requires_a_guarantor_when_the_product_demands_one(): void
    {
        $loan = Loan::factory()->for(LoanProduct::factory()->create(['requires_guarantor' => true]), 'product')->create();

        $this->actingAs($this->manager)->post(route('loans.approve', $loan))->assertSessionHasErrors('status');
        $this->assertSame(LoanStatus::Pending, $loan->fresh()->status);

        Guarantor::factory()->for($loan)->create();

        $this->actingAs($this->manager)->post(route('loans.approve', $loan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }

    public function test_approval_requires_enough_collateral_coverage(): void
    {
        $product = LoanProduct::factory()->create(['min_collateral_coverage' => 120]);
        $loan = Loan::factory()->for($product, 'product')->create(['principal_amount' => 1000]);
        Collateral::factory()->for($loan)->create(['estimated_value' => 1000]);

        $this->actingAs($this->manager)->post(route('loans.approve', $loan))->assertSessionHasErrors('status');

        Collateral::factory()->for($loan)->create(['estimated_value' => 200]);

        $this->actingAs($this->manager)->post(route('loans.approve', $loan))->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Approved, $loan->fresh()->status);
    }

    public function test_collateral_can_only_be_released_once_the_loan_is_closed(): void
    {
        $loan = Loan::factory()->create(['status' => LoanStatus::Active]);
        $collateral = Collateral::factory()->for($loan)->create();

        $this->actingAs($this->manager)->post(route('collaterals.release', $collateral))->assertSessionHas('error');
        $this->assertSame(CollateralStatus::Held, $collateral->fresh()->status);

        $loan->forceFill(['status' => LoanStatus::Closed])->save();

        $this->actingAs($this->manager)->post(route('collaterals.release', $collateral))->assertSessionHas('success');
        $collateral->refresh();
        $this->assertSame(CollateralStatus::Released, $collateral->status);
        $this->assertSame($this->manager->id, $collateral->released_by);
    }

    public function test_family_members_can_be_added_and_removed(): void
    {
        $borrower = Borrower::factory()->create(['monthly_income' => 500]);

        $this->actingAs($this->manager)->post(route('borrowers.family-members.store', $borrower), [
            'full_name' => 'Chan Sophea',
            'relationship' => 'spouse',
            'monthly_income' => 300,
            'is_dependent' => '0',
        ])->assertSessionHas('success');

        $this->actingAs($this->manager)->get(route('borrowers.show', $borrower))
            ->assertOk()
            ->assertSee('Chan Sophea')
            ->assertSee('$800.00');

        $this->actingAs($this->manager)->delete(route('family-members.destroy', FamilyMember::sole()))->assertSessionHas('success');
        $this->assertSame(0, FamilyMember::count());
    }

    public function test_staff_cannot_touch_collateral_on_loans_in_another_branch(): void
    {
        $collateral = Collateral::factory()->create();
        $otherBranchOfficer = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);

        $this->actingAs($otherBranchOfficer)->delete(route('collaterals.destroy', $collateral))->assertNotFound();
        $this->assertModelExists($collateral);
    }
}
