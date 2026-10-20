<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Borrower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrower>
 */
class BorrowerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);

        return [
            'first_name' => fake()->firstName($gender->value),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-20 years'),
            'national_id' => fake()->unique()->numerify('#########'),
            'phone' => fake()->numerify('0## ### ###'),
            'email' => fake()->optional(0.6)->safeEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Phnom Penh', 'Siem Reap', 'Battambang', 'Kampong Cham', 'Sihanoukville', 'Kampot']),
            'occupation' => fake()->randomElement(['Shop owner', 'Farmer', 'Teacher', 'Tailor', 'Driver', 'Mechanic', 'Market vendor', 'Nurse', 'Restaurant owner']),
            'employer' => fake()->optional(0.5)->company(),
            'monthly_income' => fake()->randomFloat(2, 300, 4000),
            'is_blacklisted' => false,
        ];
    }

    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes) => ['is_blacklisted' => true]);
    }
}
