<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BorrowerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Sokha',
            'last_name' => 'Meas',
            'gender' => 'female',
            'date_of_birth' => '1990-05-01',
            'national_id' => '010203040',
            'phone' => '012 345 678',
            'address' => '12 Street 271',
            'city' => 'Phnom Penh',
            'monthly_income' => '850',
        ], $overrides);
    }

    public function test_officers_can_register_a_borrower_with_a_photo(): void
    {
        Storage::fake('public');
        $officer = User::factory()->for(Branch::factory())->create();

        $response = $this->actingAs($officer)->post(route('borrowers.store'), $this->validPayload([
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]));

        $borrower = Borrower::sole();
        $response->assertRedirect(route('borrowers.show', $borrower));
        $this->assertSame('BR-'.str_pad((string) $borrower->id, 6, '0', STR_PAD_LEFT), $borrower->code);
        $this->assertSame($officer->id, $borrower->created_by);
        $this->assertSame($officer->branch_id, $borrower->branch_id);
        Storage::disk('public')->assertExists($borrower->photo_path);
    }

    public function test_national_id_must_be_unique(): void
    {
        Borrower::factory()->create(['national_id' => '010203040']);

        $this->actingAs(User::factory()->create())
            ->post(route('borrowers.store'), $this->validPayload())
            ->assertSessionHasErrors('national_id');
    }

    public function test_borrowers_must_be_adults(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('borrowers.store'), $this->validPayload(['date_of_birth' => now()->subYears(16)->toDateString()]))
            ->assertSessionHasErrors('date_of_birth');
    }

    public function test_borrower_can_be_updated_without_tripping_their_own_unique_id(): void
    {
        $borrower = Borrower::factory()->create(['national_id' => '010203040']);

        $this->actingAs(User::factory()->create())
            ->put(route('borrowers.update', $borrower), $this->validPayload(['phone' => '099 000 111']))
            ->assertRedirect(route('borrowers.show', $borrower));

        $this->assertSame('099 000 111', $borrower->fresh()->phone);
    }

    public function test_search_matches_name_and_phone(): void
    {
        Borrower::factory()->create(['first_name' => 'Rithy', 'phone' => '011 111 111']);
        Borrower::factory()->create(['first_name' => 'Bopha', 'phone' => '022 222 222']);

        $this->actingAs(User::factory()->create())
            ->get(route('borrowers.index', ['search' => 'Rithy']))
            ->assertOk()
            ->assertSee('Rithy')
            ->assertDontSee('Bopha');
    }

    public function test_officers_cannot_delete_borrowers(): void
    {
        $borrower = Borrower::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('borrowers.destroy', $borrower))->assertForbidden();
    }

    public function test_managers_cannot_delete_borrowers_with_open_loans(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->manager()->create())
            ->delete(route('borrowers.destroy', $loan->borrower_id))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('borrowers', ['id' => $loan->borrower_id]);
    }

    public function test_managers_can_delete_borrowers_without_open_loans(): void
    {
        $borrower = Borrower::factory()->create();

        $this->actingAs(User::factory()->manager()->create())
            ->delete(route('borrowers.destroy', $borrower))
            ->assertRedirect(route('borrowers.index'));

        $this->assertSoftDeleted($borrower);
    }

    public function test_documents_are_stored_privately_and_downloadable(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $borrower = Borrower::factory()->create();

        $this->actingAs($user)->post(route('borrowers.documents.store', $borrower), [
            'title' => 'National ID',
            'document' => UploadedFile::fake()->create('id.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('success');

        $document = $borrower->documents()->sole();
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($user)->get(route('documents.show', $document))->assertDownload('id.pdf');
    }
}
