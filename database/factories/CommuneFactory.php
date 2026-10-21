<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commune>
 */
class CommuneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('######'),
            'name_en' => fake()->unique()->city(),
            'name_kh' => 'Commune '.fake()->unique()->numerify('###'),
            'district_id' => District::factory(),
        ];
    }
}
