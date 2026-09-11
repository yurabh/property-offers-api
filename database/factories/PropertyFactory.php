<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->streetName() . ' Apartment',
            'city' => fake()->city(),
        ];
    }
}
