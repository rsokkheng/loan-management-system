<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\JournalType;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Borrower;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanService;
use App\Services\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-05-01');
        $this->manager = User::factory()->manager()->create();
    }

    private function disbursedLoan(LoanProduct $product, float $principal, int $term = 12): Loan
    {
        $loan = app(LoanService::class)->apply([
            'borrower_id' => Borrower::factory()->create()->id,
            'loan_product_id' => $product->id,
            'principal_amount' => $principal,
            'term' => $term,
        ], $this->manager);
        $loan->forceFill(['status' => LoanStatus::Approved])->save();

        return app(LoanService::class)->disburse($loan, $this->manager, Carbon::parse('2026-05-01'), Carbon::parse('2026-06-01'), PaymentMethod::Cash);
    }

    public function test_currencies_format_amounts_their_own_way(): void
    {
        $this->assertSame('$1,234.50', Currency::Usd->format(1234.5));
        $this->assertSame('-$5.00', Currency::Usd->format(-5));
        $this->assertSame('4,100,000៛', Currency::Khr->format(4100000));
        $this->assertSame(1235.0, Currency::Khr->round(1234.6));
    }

    public function test_a_loan_takes_its_products_currency_and_riel_schedules_use_whole_riel(): void
    {
        $product = LoanProduct::factory()->khr()->create(['interest_rate' => 18, 'processing_fee_percent' => 1]);

        $loan = $this->disbursedLoan($product, 1000000, 7);

        $this->assertSame(Currency::Khr, $loan->currency);
        $this->assertSame('10000.00', $loan->processing_fee);

        foreach ($loan->installments as $installment) {
            $this->assertSame(0.0, fmod((float) $installment->principal_due, 1), 'Principal should be whole riel');
            $this->assertSame(0.0, fmod((float) $installment->interest_due, 1), 'Interest should be whole riel');
        }

        $this->assertSame('1000000.00', number_format((float) $loan->installments->sum('principal_due'), 2, '.', ''));
    }

    public function test_ledger_entries_carry_the_loans_currency(): void
    {
        $loan = $this->disbursedLoan(LoanProduct::factory()->khr()->create(), 2000000);

        app(RepaymentService::class)->record($loan, 100000, Carbon::parse('2026-05-10'), PaymentMethod::Cash, $this->manager);

        $this->assertSame(
            [Currency::Khr, Currency::Khr],
            JournalEntry::query()->whereIn('type', [JournalType::Disbursement, JournalType::Repayment])->get()->pluck('currency')->all(),
        );
    }

    public function test_reports_and_trial_balance_only_include_the_reporting_currency(): void
    {
        $dollarLoan = $this->disbursedLoan(LoanProduct::factory()->create(), 1000);
        $rielLoan = $this->disbursedLoan(LoanProduct::factory()->khr()->create(), 4000000);

        $this->actingAs($this->manager)->get(route('reports.disbursements'))
            ->assertSee($dollarLoan->reference)
            ->assertDontSee($rielLoan->reference);

        $this->post(route('currency.switch'), ['currency' => 'KHR']);

        $this->get(route('reports.disbursements'))
            ->assertSee($rielLoan->reference)
            ->assertDontSee($dollarLoan->reference)
            ->assertSee('4,000,000៛');

        $this->get(route('accounting.trial-balance'))
            ->assertOk()
            ->assertViewHas('debit', 4000000.0);
    }

    public function test_loan_page_shows_amounts_in_the_loans_currency_regardless_of_reporting_currency(): void
    {
        $rielLoan = $this->disbursedLoan(LoanProduct::factory()->khr()->create(), 4000000);

        $this->actingAs($this->manager)->get(route('loans.show', $rielLoan))
            ->assertOk()
            ->assertSee('4,000,000៛');
    }

    public function test_product_currency_is_locked_once_it_has_loans(): void
    {
        $product = LoanProduct::factory()->create();
        Loan::factory()->for($product, 'product')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('loan-products.update', $product), ['currency' => 'KHR'] + $product->only([
                'name', 'code', 'interest_rate', 'min_amount', 'max_amount', 'min_term', 'max_term',
                'processing_fee_percent', 'late_penalty_percent', 'grace_period_days',
            ]) + ['interest_method' => $product->interest_method->value, 'repayment_frequency' => $product->repayment_frequency->value])
            ->assertSessionHasErrors('currency');

        $this->assertSame(Currency::Usd, $product->fresh()->currency);
    }

    public function test_switching_to_an_unknown_currency_is_rejected(): void
    {
        $this->actingAs($this->manager)->post(route('currency.switch'), ['currency' => 'EUR'])->assertSessionHasErrors('currency');
    }
}
