<?php

namespace Database\Factories;

use App\Enums\CollateralStatus;
use App\Enums\CollateralType;
use App\Models\Collateral;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collateral>
 */
class CollateralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'type' => CollateralType::Motorbike,
            'description' => 'Honda Dream 2020',
            'document_number' => fake()->bothify('PP-####'),
            'owner_name' => fake()->name(),
            'estimated_value' => 1500,
            'valuation_date' => today(),
            'status' => CollateralStatus::Held,
        ];
    }
}
