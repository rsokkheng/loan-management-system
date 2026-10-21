<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Branch;
use App\Models\Commune;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = User::factory()->for(Branch::factory())->create();

        $province = Province::factory()->create(['name_en' => 'Siem Reap', 'name_kh' => 'សៀមរាប']);
        $district = District::factory()->for($province)->create(['name_en' => 'Siem Reab', 'name_kh' => 'សៀមរាប']);
        $commune = Commune::factory()->for($district)->create(['name_en' => 'Sla Kram', 'name_kh' => 'ស្លក្រាម']);
        $this->village = Village::factory()->for($commune)->create(['name_en' => 'Trapeang Ses', 'name_kh' => 'ត្រពាំងសេះ']);
    }

    /**
     * @return array<string, mixed>
     */
    private function borrowerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Sokha', 'last_name' => 'Meas', 'gender' => 'female', 'national_id' => '010203040',
            'phone' => '012 345 678', 'address' => 'House 12, Street 6',
            'province_id' => $this->village->commune->district->province_id,
            'district_id' => $this->village->commune->district_id,
            'commune_id' => $this->village->commune_id,
            'village_id' => $this->village->id,
        ], $overrides);
    }

    public function test_dropdown_endpoints_return_the_children_of_the_selected_parent(): void
    {
        $commune = $this->village->commune;
        Village::factory()->create(['name_en' => 'Elsewhere']);

        $this->actingAs($this->officer)
            ->getJson(route('locations.villages', ['parent' => $commune->id]))
            ->assertOk()
            ->assertExactJson([['id' => $this->village->id, 'name' => 'Trapeang Ses']]);

        $this->actingAs($this->officer)
            ->getJson(route('locations.communes', ['parent' => $commune->district_id]))
            ->assertExactJson([['id' => $commune->id, 'name' => 'Sla Kram']]);
    }

    public function test_dropdowns_show_khmer_names_in_khmer(): void
    {
        $this->officer->update(['locale' => 'km']);

        $this->actingAs($this->officer)
            ->getJson(route('locations.villages', ['parent' => $this->village->commune_id]))
            ->assertExactJson([['id' => $this->village->id, 'name' => 'ត្រពាំងសេះ']]);
    }

    public function test_inactive_and_deleted_locations_are_not_offered(): void
    {
        $commune = $this->village->commune;
        Village::factory()->for($commune)->create(['record_status_id' => 2]);
        Village::factory()->for($commune)->create()->delete();

        $this->actingAs($this->officer)
            ->getJson(route('locations.villages', ['parent' => $commune->id]))
            ->assertJsonCount(1);
    }

    public function test_active_locations_with_a_placeholder_end_date_are_offered(): void
    {
        $this->village->forceFill(['end_date' => '2000-01-01'])->save();

        $this->actingAs($this->officer)
            ->getJson(route('locations.villages', ['parent' => $this->village->commune_id]))
            ->assertJsonCount(1);
    }

    public function test_dropdown_endpoints_require_sign_in(): void
    {
        $this->getJson(route('locations.districts', ['parent' => 1]))->assertUnauthorized();
    }

    public function test_borrowers_are_saved_with_their_full_address(): void
    {
        $this->actingAs($this->officer)->post(route('borrowers.store'), $this->borrowerPayload())->assertSessionHasNoErrors();

        $borrower = Borrower::sole();
        $this->assertSame($this->village->id, $borrower->village_id);
        $this->assertSame('House 12, Street 6, Trapeang Ses, Sla Kram, Siem Reab, Siem Reap', $borrower->full_address);

        $this->actingAs($this->officer)->get(route('borrowers.show', $borrower))->assertSee('House 12, Street 6, Trapeang Ses, Sla Kram, Siem Reab, Siem Reap');
    }

    public function test_borrowers_need_a_complete_address(): void
    {
        $this->actingAs($this->officer)
            ->post(route('borrowers.store'), $this->borrowerPayload(['village_id' => null, 'commune_id' => null]))
            ->assertSessionHasErrors(['commune_id', 'village_id']);
    }

    public function test_each_level_must_belong_to_the_level_above(): void
    {
        $otherVillage = Village::factory()->create();

        $this->actingAs($this->officer)
            ->post(route('borrowers.store'), $this->borrowerPayload(['village_id' => $otherVillage->id]))
            ->assertSessionHasErrors('village_id');

        $this->assertDatabaseCount('borrowers', 0);
    }

    public function test_branch_address_is_optional(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('branches.store'), ['name' => 'Kampot Branch', 'code' => 'KP', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertNull(Branch::query()->where('code', 'KP')->value('province_id'));
    }

    public function test_edit_form_preloads_the_saved_address_levels(): void
    {
        $this->actingAs($this->officer)->post(route('borrowers.store'), $this->borrowerPayload());

        $this->actingAs($this->officer)->get(route('borrowers.edit', Borrower::sole()))
            ->assertOk()
            ->assertSee('Trapeang Ses')
            ->assertSee('Sla Kram');
    }
}
