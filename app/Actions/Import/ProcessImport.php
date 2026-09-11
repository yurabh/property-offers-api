<?php

namespace App\Actions\Import;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessImport
{
    public function handle(Import $import): void
    {
        foreach ($import->offerRows() as $row) {
            DB::transaction(fn() => $this->importRow($import, $row));

            $import->increment('processed_offers');
        }
    }

    private function importRow(Import $import, array $row): void
    {
        $property = $this->findOrCreateProperty($row['property']);

        $this->upsertOffer($import, $property, $row);
    }

    private function findOrCreateProperty(array $property): Property
    {
        return Property::firstOrCreate(
            ['code' => $property['code']],
            [
                'name' => $property['name'],
                'city' => $property['city'],
            ],
        );
    }

    private function upsertOffer(Import $import, Property $property, array $row): void
    {
        $identity = [
            'supplier_id' => $import->supplier_id,
            'external_id' => $row['external_id'],
        ];

        Offer::updateOrCreate($identity, $this->offerAttributes($import, $property, $row));
    }

    private function offerAttributes(Import $import, Property $property, array $row): array
    {
        return [
            'property_id' => $property->id,
            'import_id' => $import->id,
            'check_in' => $row['check_in'],
            'check_out' => $row['check_out'],
            'max_guests' => $row['max_guests'],
            'price' => $row['price'],
            'currency' => $row['currency'],
            'available_units' => $row['available_units'],
            'expires_at' => Carbon::parse($row['expires_at']),
            'sent_at' => $import->sent_at,
        ];
    }
}
