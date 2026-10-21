<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
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
            'name_kh' => 'District '.fake()->unique()->numerify('###'),
            'province_id' => Province::factory(),
        ];
    }
}
