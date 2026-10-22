<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates a pending application. Use LoanService to approve/disburse so
 * schedules and totals stay consistent.
 *
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = LoanProduct::factory();

        return [
            'borrower_id' => Borrower::factory(),
            'loan_product_id' => $product,
            'currency' => fn (array $attributes) => LoanProduct::find($attributes['loan_product_id'])->currency,
            'principal_amount' => 1200,
            'interest_rate' => 24,
            'interest_method' => fn (array $attributes) => LoanProduct::find($attributes['loan_product_id'])->interest_method,
            'repayment_frequency' => fn (array $attributes) => LoanProduct::find($attributes['loan_product_id'])->repayment_frequency,
            'schedule_type' => fn (array $attributes) => LoanProduct::find($attributes['loan_product_id'])->schedule_type,
            'term' => 12,
            'processing_fee' => 12,
            'late_penalty_percent' => 5,
            'grace_period_days' => 3,
            'total_interest' => 0,
            'total_payable' => 1200,
            'status' => LoanStatus::Pending,
            'application_date' => today(),
        ];
    }
}
