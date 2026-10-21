<?php

namespace Database\Factories;

use App\Models\Commune;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Village>
 */
class VillageFactory extends Factory
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
            'name_kh' => 'Village '.fake()->unique()->numerify('###'),
            'commune_id' => Commune::factory(),
        ];
    }
}
