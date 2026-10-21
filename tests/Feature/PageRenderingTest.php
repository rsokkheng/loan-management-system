<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Renders every screen against the demo dataset to catch view errors and N+1 lazy loading.
 */
class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string>}>
     */
    public static function adminPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'calculator' => ['calculator'],
            'calculator with result' => ['calculator', ['principal' => '5000', 'interest_rate' => '18', 'term' => '12', 'interest_method' => 'reducing_balance', 'repayment_frequency' => 'monthly', 'first_due_date' => '2026-12-01']],
            'borrowers' => ['borrowers.index'],
            'new borrower' => ['borrowers.create'],
            'loans' => ['loans.index'],
            'overdue loans' => ['loans.index', ['status' => 'overdue']],
            'new loan' => ['loans.create'],
            'payments' => ['payments.index'],
            'receive payment' => ['payments.create'],
            'loan products' => ['loan-products.index'],
            'new loan product' => ['loan-products.create'],
            'reports' => ['reports.index'],
            'collections report' => ['reports.collections'],
            'disbursements report' => ['reports.disbursements'],
            'arrears report' => ['reports.arrears'],
            'portfolio report' => ['reports.portfolio'],
            'users' => ['users.index'],
            'new user' => ['users.create'],
            'settings' => ['settings.edit'],
            'activity log' => ['activity-logs.index'],
            'profile' => ['profile.edit'],
            'branches' => ['branches.index'],
            'new branch' => ['branches.create'],
            'chart of accounts' => ['accounting.accounts.index'],
            'new account' => ['accounting.accounts.create'],
            'journal' => ['accounting.journal.index'],
            'new journal entry' => ['accounting.journal.create'],
            'record expense' => ['accounting.transactions.create', ['type' => 'expense']],
            'record income' => ['accounting.transactions.create', ['type' => 'income']],
            'trial balance' => ['accounting.trial-balance'],
            'income statement' => ['accounting.income-statement'],
            'balance sheet' => ['accounting.balance-sheet'],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('adminPages')]
    public function test_admin_pages_render(string $route, array $query = []): void
    {
        $this->actingAs($this->admin())->get(route($route, $query))->assertOk();
    }

    #[DataProvider('adminPages')]
    public function test_admin_pages_render_in_khmer_for_a_single_branch(string $route, array $query = []): void
    {
        $admin = $this->admin();
        $admin->update(['locale' => 'km']);
        $this->actingAs($admin)->post(route('branches.switch'), ['branch_id' => Branch::query()->value('id')]);

        $this->actingAs($admin)->get(route($route, $query))->assertOk()->assertSee('lang="km"', false);
    }

    public function test_record_pages_render_for_every_loan_status(): void
    {
        $admin = $this->admin();

        foreach (Loan::query()->get()->unique(fn (Loan $loan) => $loan->status->value) as $loan) {
            $this->actingAs($admin)->get(route('loans.show', $loan))->assertOk()->assertSee($loan->reference);
        }

        $loan = Loan::query()->whereNotNull('disbursed_at')->first();
        $this->actingAs($admin)->get(route('loans.print', $loan))->assertOk();

        $pending = Loan::query()->where('status', 'pending')->first();
        $this->actingAs($admin)->get(route('loans.edit', $pending))->assertOk();

        $borrower = Borrower::query()->has('loans')->first();
        $this->actingAs($admin)->get(route('borrowers.show', $borrower))->assertOk();
        $this->actingAs($admin)->get(route('borrowers.edit', $borrower))->assertOk();

        $payment = Payment::query()->first();
        $this->actingAs($admin)->get(route('payments.show', $payment))->assertOk();
        $this->actingAs($admin)->get(route('payments.receipt', $payment))->assertOk();

        $this->actingAs($admin)->get(route('loan-products.edit', $loan->loan_product_id))->assertOk();
        $this->actingAs($admin)->get(route('branches.edit', Branch::query()->first()))->assertOk();

        $account = Account::system('cash');
        $this->actingAs($admin)->get(route('accounting.accounts.show', $account))->assertOk();
        $this->actingAs($admin)->get(route('accounting.accounts.edit', $account))->assertOk();
        foreach (JournalEntry::query()->get()->unique(fn (JournalEntry $entry) => $entry->type->value) as $entry) {
            $this->actingAs($admin)->get(route('accounting.journal.show', $entry))->assertOk()->assertSee($entry->reference);
        }
        $this->actingAs($admin)->get(route('users.edit', $admin))->assertOk();
    }

    public function test_loan_officers_do_not_see_admin_menus(): void
    {
        $officer = User::query()->where('email', 'officer@loanpro.test')->sole();

        $this->actingAs($officer)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('settings.edit'));

        $this->actingAs($officer)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('loan-products.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('users.index'))->assertForbidden();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@loanpro.test')->sole();
    }
}
