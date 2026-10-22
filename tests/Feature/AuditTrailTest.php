<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_record_before_and_after_values(): void
    {
        $admin = User::factory()->admin()->create();
        $loan = Loan::factory()->create(['principal_amount' => 1200, 'term' => 12]);

        $this->actingAs($admin)->put(route('loans.update', $loan), [
            'loan_product_id' => $loan->loan_product_id,
            'principal_amount' => 2400,
            'term' => 12,
            'application_date' => today()->toDateString(),
        ]);

        $log = ActivityLog::query()->where('action', 'updated')->sole();
        $this->assertEquals(1200, $log->changes['principal_amount']['old']);
        $this->assertEquals(2400, $log->changes['principal_amount']['new']);
        $this->assertArrayNotHasKey('updated_at', $log->changes);
        $this->assertArrayNotHasKey('term', $log->changes);
    }

    public function test_status_changes_are_captured_on_workflow_actions(): void
    {
        $loan = Loan::factory()->create();

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.approve', $loan));

        $log = ActivityLog::query()->where('action', 'approved')->sole();
        $this->assertSame(['old' => 'pending', 'new' => 'approved'], $log->changes['status']);
    }

    public function test_passwords_are_never_written_to_the_audit_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'New Name',
            'email' => $user->email,
            'role' => $user->role->value,
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
            'is_active' => '1',
        ]);

        $log = ActivityLog::query()->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('New Name', $log->changes['name']['new']);
        $this->assertArrayNotHasKey('password', $log->changes);
        $this->assertStringNotContainsString('new-secret-password', json_encode($log->changes));
    }

    public function test_settings_changes_are_recorded(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.update'), ['company_name' => 'Acme Finance', 'currency_code' => 'USD']);

        $log = ActivityLog::query()->where('description', 'System settings updated')->sole();
        $this->assertSame('Acme Finance', $log->changes['company_name']['new']);
        $this->assertArrayNotHasKey('currency_code', $log->changes);
    }

    public function test_admin_can_view_the_history_of_one_borrower(): void
    {
        $admin = User::factory()->admin()->create();
        $borrower = Borrower::factory()->create();
        $other = Borrower::factory()->create();
        ActivityLog::record('updated', 'Borrower A changed', $borrower);
        ActivityLog::record('updated', 'Borrower B changed', $other);

        $this->actingAs($admin)->get(route('activity-logs.index', ['borrower' => $borrower->id]))
            ->assertOk()
            ->assertSee('Borrower A changed')
            ->assertDontSee('Borrower B changed');
    }
}
