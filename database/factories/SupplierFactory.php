<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        $code = 'supplier-' . fake()->unique()->bothify('??##');

        return [
            'code' => $code,
            'name' => ucfirst(str_replace('-', ' ', $code)),
        ];
    }
}
