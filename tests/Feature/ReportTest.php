<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
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

    public function test_loan_officers_cannot_view_reports(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.collections'))->assertForbidden();
    }
}
