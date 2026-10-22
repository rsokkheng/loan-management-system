<?php

namespace Database\Factories;

use App\Enums\Relationship;
use App\Models\Borrower;
use App\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'borrower_id' => Borrower::factory(),
            'full_name' => fake()->name(),
            'relationship' => fake()->randomElement(Relationship::cases()),
            'phone' => fake()->numerify('0#########'),
            'occupation' => fake()->jobTitle(),
            'monthly_income' => fake()->numberBetween(0, 800),
            'is_dependent' => false,
        ];
    }
}
