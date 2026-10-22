<?php

namespace Tests\Feature;

use App\Enums\JournalType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\LoanService;
use App\Services\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_report_exports_payments_in_range_as_csv(): void
    {
        $this->travelTo('2026-06-15');
        $manager = User::factory()->manager()->create();
        $product = LoanProduct::factory()->flat()->create();
        $loan = Loan::factory()->for($product, 'product')->create(['status' => LoanStatus::Approved]);
        app(LoanService::class)->disburse($loan, $manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);
        $payment = app(RepaymentService::class)->record($loan, 50, Carbon::parse('2026-06-10'), PaymentMethod::Cash, $manager);

        $response = $this->actingAs($manager)->get(route('reports.collections', ['from' => '2026-06-01', 'to' => '2026-06-30', 'export' => 'csv']));

        $response->assertOk()->assertDownload('collections-2026-06-01-to-2026-06-30.csv');
        $this->assertStringContainsString($payment->receipt_number, $response->streamedContent());
    }

    public function test_arrears_report_buckets_overdue_loans(): void
    {
        $this->travelTo('2026-05-01');
        $manager = User::factory()->manager()->create();
        $loan = Loan::factory()->create(['status' => LoanStatus::Approved]);
        app(LoanService::class)->disburse($loan, $manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);

        $this->travelTo('2026-06-20');

        $this->actingAs($manager)->get(route('reports.arrears'))
            ->assertOk()
            ->assertSee($loan->reference)
            ->assertViewHas('buckets', fn ($buckets) => $buckets['1-30']['loans'] === 1 && $buckets['31-60']['loans'] === 0);
    }

    public function test_portfolio_at_risk_uses_outstanding_principal_of_loans_late_beyond_each_threshold(): void
    {
        $this->travelTo('2026-05-01');
        $manager = User::factory()->manager()->create();
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);
        $loans = Loan::factory()->count(2)->for($product, 'product')->create(['principal_amount' => 1200, 'term' => 12, 'status' => LoanStatus::Approved]);

        foreach ($loans as $loan) {
            app(LoanService::class)->disburse($loan, $manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);
        }

        // The second loan pays its first installment (100 principal) and is up to date until 2026-07-01.
        app(RepaymentService::class)->record($loans[1], 124, Carbon::parse('2026-06-01'), PaymentMethod::Cash, $manager);

        // 2026-07-05: loan 1 is 34 days late, loan 2 is 4 days late.
        $this->travelTo('2026-07-05');

        $this->actingAs($manager)->get(route('reports.portfolio-at-risk'))
            ->assertOk()
            ->assertSee($loans[0]->reference)
            ->assertViewHas('summary', function (array $summary) {
                return $summary['outstanding'] === 2300.0
                    && $summary['par'][1]['loans'] === 2
                    && $summary['par'][1]['percent'] === 100.0
                    && $summary['par'][30]['principal'] === 1200.0
                    && $summary['par'][30]['percent'] === 52.17
                    && $summary['par'][60]['loans'] === 0;
            });
    }

    public function test_portfolio_at_risk_is_zero_when_no_loan_is_late(): void
    {
        $this->travelTo('2026-05-01');
        $manager = User::factory()->manager()->create();
        $loan = Loan::factory()->create(['status' => LoanStatus::Approved]);
        app(LoanService::class)->disburse($loan, $manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);

        $this->actingAs($manager)->get(route('reports.portfolio-at-risk'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary) => $summary['par'][1]['percent'] === 0.0 && $summary['loans'] === 1);
    }

    public function test_branch_profitability_splits_income_and_expenses_by_branch(): void
    {
        $this->travelTo('2026-06-15');
        $admin = User::factory()->admin()->create();
        [$north, $south] = Branch::factory()->count(2)->create();
        $product = LoanProduct::factory()->flat()->create(['interest_rate' => 24, 'processing_fee_percent' => 1]);

        $loan = Loan::factory()->for($product, 'product')->create([
            'branch_id' => $north->id, 'principal_amount' => 1200, 'term' => 12, 'processing_fee' => 12, 'status' => LoanStatus::Approved,
        ]);
        app(LoanService::class)->disburse($loan, $admin, Carbon::parse('2026-06-01'), Carbon::parse('2026-07-01'), PaymentMethod::Cash);
        app(RepaymentService::class)->record($loan, 124, Carbon::parse('2026-06-10'), PaymentMethod::Cash, $admin);

        app(LedgerService::class)->post(
            JournalType::Expense, Carbon::parse('2026-06-05'), 'Rent',
            [['account' => Account::query()->where('code', '5010')->value('id'), 'debit' => 50], ['account' => Account::system('cash'), 'credit' => 50]],
            $south->id,
        );

        $this->actingAs($admin)->get(route('reports.branch-profitability', ['from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertViewHas('rows', function ($rows) use ($north, $south) {
                $northRow = $rows->firstWhere('name', $north->name);
                $southRow = $rows->firstWhere('name', $south->name);

                return $northRow['interest'] === 24.0
                    && $northRow['fees'] === 12.0
                    && $northRow['net'] === 36.0
                    && $northRow['active_loans'] === 1
                    && $northRow['outstanding'] === 1100.0
                    && $northRow['disbursed'] === 1200.0
                    && $southRow['operating'] === 50.0
                    && $southRow['net'] === -50.0;
            })
            ->assertViewHas('totals', fn (array $totals) => $totals['net'] === -14.0);
    }

    public function test_loan_officers_cannot_view_reports(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.collections'))->assertForbidden();
    }
}
