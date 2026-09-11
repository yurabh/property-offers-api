<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'import_id' => null,
            'external_id' => 'offer-' . fake()->unique()->bothify('########'),
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => fake()->numberBetween(50_000, 150_000),
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn() => ['expires_at' => now()->subMinute()]);
    }

    public function soldOut(): static
    {
        return $this->state(fn() => ['available_units' => 0]);
    }
}
