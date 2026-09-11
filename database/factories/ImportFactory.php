<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'external_import_id' => 'import-' . fake()->unique()->bothify('####-????'),
            'sent_at' => now(),
            'status' => ImportStatus::Pending,
            'payload' => ['offers' => []],
            'total_offers' => 0,
            'processed_offers' => 0,
        ];
    }

    public function withOffers(array $offers): static
    {
        return $this->state(fn() => [
            'payload' => ['offers' => $offers],
            'total_offers' => count($offers),
        ]);
    }
}
