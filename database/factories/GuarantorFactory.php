<?php

namespace Database\Factories;

use App\Enums\Relationship;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guarantor>
 */
class GuarantorFactory extends Factory
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
            'full_name' => fake()->name(),
            'relationship' => Relationship::Relative,
            'national_id' => fake()->unique()->numerify('#########'),
            'phone' => fake()->numerify('0#########'),
            'occupation' => fake()->jobTitle(),
            'monthly_income' => fake()->numberBetween(200, 1500),
        ];
    }
}
