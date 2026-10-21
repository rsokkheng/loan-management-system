<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Village;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BranchScopingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $phnomPenh;

    private Branch $siemReap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phnomPenh = Branch::factory()->create(['name' => 'Phnom Penh']);
        $this->siemReap = Branch::factory()->create(['name' => 'Siem Reap']);
    }

    public function test_branch_staff_only_see_their_own_borrowers(): void
    {
        $officer = User::factory()->for($this->phnomPenh)->create();
        $own = Borrower::factory()->for($this->phnomPenh)->create(['first_name' => 'Sokun']);
        $other = Borrower::factory()->for($this->siemReap)->create(['first_name' => 'Rotha']);

        $this->actingAs($officer)->get(route('borrowers.index'))
            ->assertOk()
            ->assertSee('Sokun')
            ->assertDontSee('Rotha');

        $this->actingAs($officer)->get(route('borrowers.show', $own))->assertOk();
        $this->actingAs($officer)->get(route('borrowers.show', $other))->assertNotFound();
    }

    public function test_branch_staff_cannot_open_loans_or_record_payments_in_other_branches(): void
    {
        $officer = User::factory()->for($this->phnomPenh)->create();
        $loan = Loan::factory()->create([
            'branch_id' => $this->siemReap->id,
            'borrower_id' => Borrower::factory()->for($this->siemReap),
        ]);

        $this->actingAs($officer)->get(route('loans.show', $loan))->assertNotFound();
        $this->actingAs($officer)->post(route('payments.store', $loan), ['amount' => 10, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertNotFound();
    }

    public function test_branch_staff_cannot_switch_branches(): void
    {
        $officer = User::factory()->for($this->phnomPenh)->create();

        $this->actingAs($officer)->post(route('branches.switch'), ['branch_id' => $this->siemReap->id])->assertForbidden();
    }

    public function test_admins_see_all_branches_and_can_focus_on_one(): void
    {
        $admin = User::factory()->admin()->create();
        Borrower::factory()->for($this->phnomPenh)->create(['first_name' => 'Sokun']);
        Borrower::factory()->for($this->siemReap)->create(['first_name' => 'Rotha']);

        $this->actingAs($admin)->get(route('borrowers.index'))->assertSee('Sokun')->assertSee('Rotha');

        $this->actingAs($admin)->post(route('branches.switch'), ['branch_id' => $this->siemReap->id])->assertRedirect();

        $this->actingAs($admin)->get(route('borrowers.index'))->assertDontSee('Sokun')->assertSee('Rotha');
    }

    public function test_admins_must_pick_a_branch_when_registering_borrowers_from_all_branches(): void
    {
        $payload = [
            'first_name' => 'Dara', 'last_name' => 'Ouk', 'gender' => 'male', 'national_id' => '123456789',
            'phone' => '012 000 000', 'address' => 'Street 1', ...$this->villageAddress(),
        ];
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('borrowers.store'), $payload)->assertSessionHasErrors('branch_id');

        $this->actingAs($admin)->post(route('borrowers.store'), $payload + ['branch_id' => $this->siemReap->id])->assertSessionHasNoErrors();
        $this->assertSame($this->siemReap->id, Borrower::sole()->branch_id);
    }

    public function test_branch_staff_cannot_create_borrowers_in_another_branch(): void
    {
        $officer = User::factory()->for($this->phnomPenh)->create();

        $this->actingAs($officer)->post(route('borrowers.store'), [
            'first_name' => 'Dara', 'last_name' => 'Ouk', 'gender' => 'male', 'national_id' => '123456789',
            'phone' => '012 000 000', 'address' => 'Street 1', ...$this->villageAddress(), 'branch_id' => $this->siemReap->id,
        ]);

        $this->assertSame($this->phnomPenh->id, Borrower::sole()->branch_id);
    }

    public function test_loans_and_payments_inherit_the_borrowers_branch(): void
    {
        $manager = User::factory()->manager()->for($this->siemReap)->create();
        $borrower = Borrower::factory()->for($this->siemReap)->create();
        $product = LoanProduct::factory()->create();

        $loan = app(LoanService::class)->apply([
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'principal_amount' => 1000,
            'term' => 6,
        ], $manager);

        $this->assertSame($this->siemReap->id, $loan->branch_id);

        $this->actingAs($manager);
        $loan->forceFill(['status' => LoanStatus::Approved])->save();
        app(LoanService::class)->disburse($loan, $manager, Carbon::today()->subMonth(), Carbon::today(), PaymentMethod::Cash);

        $this->post(route('payments.store', $loan), ['amount' => 50, 'payment_date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasNoErrors();

        $this->assertSame($this->siemReap->id, $loan->payments()->sole()->branch_id);
    }

    /**
     * @return array<string, int>
     */
    private function villageAddress(): array
    {
        $village = Village::factory()->create();

        return [
            'province_id' => $village->commune->district->province_id,
            'district_id' => $village->commune->district_id,
            'commune_id' => $village->commune_id,
            'village_id' => $village->id,
        ];
    }

    public function test_admins_can_manage_branches_but_managers_cannot(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get(route('branches.index'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('branches.store'), ['name' => 'Kampot Branch', 'code' => 'kp', 'is_active' => '1'])
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['name' => 'Kampot Branch', 'code' => 'KP']);
    }

    public function test_branches_in_use_cannot_be_deleted(): void
    {
        User::factory()->for($this->phnomPenh)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('branches.destroy', $this->phnomPenh))
            ->assertSessionHas('error');

        $this->assertModelExists($this->phnomPenh);
    }
}
