<?php

namespace Tests\Feature;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\ScheduleType;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\LoanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FlexibleScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private LoanProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-01-01');
        $this->officer = User::factory()->create();
        $this->product = LoanProduct::factory()->flexible()->create([
            'interest_rate' => 36, 'interest_method' => InterestMethod::ReducingBalance, 'processing_fee_percent' => 0,
        ]);
    }

    /**
     * Two interest-only months, then the whole principal (a harvest-time balloon).
     *
     * @return list<array{due_date: string, principal: int}>
     */
    private function balloonPlan(): array
    {
        return [
            ['due_date' => '2026-01-31', 'principal' => 0],
            ['due_date' => '2026-03-02', 'principal' => 0],
            ['due_date' => '2026-04-01', 'principal' => 1000],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function application(array $plan): array
    {
        return [
            'borrower_id' => Borrower::factory()->create()->id,
            'loan_product_id' => $this->product->id,
            'principal_amount' => 1000,
            'application_date' => '2026-01-01',
            'plan' => $plan,
        ];
    }

    public function test_interest_accrues_by_actual_days_on_the_remaining_balance(): void
    {
        $quote = app(LoanCalculator::class)->flexibleQuote(1000, 36, InterestMethod::ReducingBalance, Carbon::parse('2026-01-01'), [
            ['due_date' => '2026-01-31', 'principal' => 500],
            ['due_date' => '2026-03-02', 'principal' => 500],
        ]);

        // 30 days on 1,000 then 30 days on 500, at 36% / 365.
        $this->assertSame([29.59, 14.79], $quote->schedule->pluck('interest')->all());
        $this->assertSame([500.0, 0.0], $quote->schedule->pluck('balanceAfter')->all());
    }

    public function test_a_flexible_application_stores_its_plan_and_derives_the_term(): void
    {
        $this->actingAs($this->officer)->post(route('loans.store'), $this->application($this->balloonPlan()))
            ->assertSessionHasNoErrors();

        $loan = Loan::sole();
        $this->assertSame(ScheduleType::Flexible, $loan->schedule_type);
        $this->assertSame(3, $loan->term);
        $this->assertSame('2026-04-01', $loan->flexible_plan[2]['due_date']);
        $this->assertSame('88.77', $loan->total_interest);
    }

    public function test_the_plan_principal_must_add_up_to_the_loan_amount(): void
    {
        $plan = $this->balloonPlan();
        $plan[2]['principal'] = 900;

        $this->actingAs($this->officer)->post(route('loans.store'), $this->application($plan))
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, Loan::count());
    }

    public function test_due_dates_must_move_forward(): void
    {
        $plan = $this->balloonPlan();
        $plan[1]['due_date'] = '2026-01-15';

        $this->actingAs($this->officer)->post(route('loans.store'), $this->application($plan))
            ->assertSessionHasErrors('plan.1.due_date');
    }

    public function test_disbursement_keeps_the_agreed_due_dates_and_accrues_from_the_disbursement_date(): void
    {
        $this->actingAs($this->officer)->post(route('loans.store'), $this->application($this->balloonPlan()));
        $loan = Loan::sole();
        $loan->forceFill(['status' => LoanStatus::Approved])->save();
        $this->travelTo('2026-01-11');

        $this->actingAs(User::factory()->manager()->create())->post(route('loans.disburse', $loan), [
            'disbursed_at' => '2026-01-11',
            'first_repayment_date' => '2026-01-31',
            'disbursement_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $loan->refresh();
        $installments = $loan->installments()->get();
        $this->assertSame(['2026-01-31', '2026-03-02', '2026-04-01'], $installments->map(fn ($i) => $i->due_date->toDateString())->all());
        $this->assertSame(['0.00', '0.00', '1000.00'], $installments->pluck('principal_due')->all());
        // First period is now 20 days (11 Jan → 31 Jan) instead of 30.
        $this->assertSame('19.73', $installments[0]->interest_due);
        $this->assertSame('2026-04-01', $loan->maturity_date->toDateString());
    }

    public function test_fixed_products_ignore_any_plan_rows(): void
    {
        $fixed = LoanProduct::factory()->flat()->create(['interest_rate' => 24]);

        $this->actingAs($this->officer)->post(route('loans.store'), [
            'borrower_id' => Borrower::factory()->create()->id,
            'loan_product_id' => $fixed->id,
            'principal_amount' => 1200,
            'term' => 12,
            'application_date' => '2026-01-01',
            'plan' => $this->balloonPlan(),
        ])->assertSessionHasNoErrors();

        $loan = Loan::sole();
        $this->assertSame(ScheduleType::Fixed, $loan->schedule_type);
        $this->assertSame(12, $loan->term);
        $this->assertNull($loan->flexible_plan);
    }

    public function test_application_form_offers_the_plan_editor(): void
    {
        $this->actingAs($this->officer)->get(route('loans.create'))
            ->assertOk()
            ->assertSee('data-schedule="flexible"', false)
            ->assertSee('id="flexible-plan"', false);
    }
}
