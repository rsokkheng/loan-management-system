<?php

namespace Database\Factories;

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Loan',
            'code' => fake()->unique()->lexify('???'),
            'description' => fake()->sentence(),
            'interest_rate' => 24,
            'interest_method' => InterestMethod::ReducingBalance,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'min_amount' => 100,
            'max_amount' => 50000,
            'min_term' => 1,
            'max_term' => 36,
            'processing_fee_percent' => 1,
            'late_penalty_percent' => 5,
            'grace_period_days' => 3,
            'is_active' => true,
        ];
    }

    public function flat(): static
    {
        return $this->state(fn (array $attributes) => ['interest_method' => InterestMethod::Flat]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
