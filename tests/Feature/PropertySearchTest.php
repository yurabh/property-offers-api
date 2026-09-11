<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplierA;

    private Supplier $supplierB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplierA = Supplier::factory()->create(['code' => 'supplier-a']);
        $this->supplierB = Supplier::factory()->create(['code' => 'supplier-b']);
    }

    public function test_it_returns_the_cheapest_matching_offer_per_property(): void
    {
        $property = $this->property('BCN-0001', 'Barcelona');

        $this->offer($property, $this->supplierA, ['price' => 72500]);
        $cheapest = $this->offer($property, $this->supplierB, ['price' => 61000]);
        $this->offer($property, $this->supplierA, ['price' => 88000, 'external_id' => 'offer-a-3']);

        $response = $this->getJson('/api/properties?' . $this->query());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('data.0.name', 'Apartment near Sagrada Familia')
            ->assertJsonPath('data.0.city', 'Barcelona')
            ->assertJsonPath('data.0.best_offer.id', $cheapest->id)
            ->assertJsonPath('data.0.best_offer.supplier', 'supplier-b')
            ->assertJsonPath('data.0.best_offer.price', 61000)
            ->assertJsonPath('data.0.best_offer.currency', 'EUR')
            ->assertJsonPath('data.0.best_offer.available_units', 2);
    }

    public function test_it_ignores_offers_that_are_not_bookable(): void
    {
        $property = $this->property('BCN-0001', 'Barcelona');

        $this->offer($property, $this->supplierA, ['price' => 10000], 'expired');
        $this->offer($property, $this->supplierB, ['price' => 11000, 'external_id' => 'sold'], 'soldOut');
        $this->offer($property, $this->supplierA, ['price' => 12000, 'external_id' => 'small', 'max_guests' => 1]);
        $this->offer($property, $this->supplierB, ['price' => 13000, 'external_id' => 'dates', 'check_in' => '2026-11-01', 'check_out' => '2026-11-05']);

        $valid = $this->offer($property, $this->supplierA, ['price' => 90000, 'external_id' => 'ok']);

        $this->getJson('/api/properties?' . $this->query())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.best_offer.id', $valid->id)
            ->assertJsonPath('data.0.best_offer.price', 90000);
    }

    public function test_a_property_without_bookable_offers_is_absent(): void
    {
        $property = $this->property('BCN-0002', 'Barcelona');
        $this->offer($property, $this->supplierA, [], 'expired');

        $this->getJson('/api/properties?' . $this->query())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_it_filters_by_city(): void
    {
        $barcelona = $this->property('BCN-0001', 'Barcelona');
        $madrid = $this->property('MAD-0001', 'Madrid');

        $this->offer($barcelona, $this->supplierA, ['price' => 72500]);
        $this->offer($madrid, $this->supplierA, ['price' => 30000, 'external_id' => 'mad-1']);

        $this->getJson('/api/properties?' . $this->query())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001');

        $this->getJson('/api/properties?' . $this->query(withCity: false))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'MAD-0001');
    }

    public function test_results_are_sorted_by_price_and_paginated(): void
    {
        foreach ([['BCN-0001', 50000], ['BCN-0002', 30000], ['BCN-0003', 40000]] as [$code, $price]) {
            $property = $this->property($code, 'Barcelona');
            $this->offer($property, $this->supplierA, ['price' => $price, 'external_id' => 'offer-' . $code]);
        }

        $response = $this->getJson('/api/properties?' . $this->query() . '&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0002')
            ->assertJsonPath('data.1.code', 'BCN-0003')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('links.prev', null)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->assertNotNull($response->json('links.next'));

        $this->getJson('/api/properties?' . $this->query() . '&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('links.next', null);
    }

    public function test_it_validates_search_parameters(): void
    {
        $this->getJson('/api/properties')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['check_in', 'check_out']);

        $this->getJson('/api/properties?check_in=2026-10-15&check_out=2026-10-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_out');
    }

    private function query(bool $withCity = true): string
    {
        return http_build_query(array_filter([
            'city' => $withCity ? 'Barcelona' : null,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]));
    }

    private function property(string $code, string $city): Property
    {
        return Property::factory()->create([
            'code' => $code,
            'name' => 'Apartment near Sagrada Familia',
            'city' => $city,
        ]);
    }

    private function offer(Property $property, Supplier $supplier, array $attributes = [], ?string $state = null): Offer
    {
        $factory = Offer::factory()->for($property)->for($supplier);

        if ($state !== null) {
            $factory = $factory->{$state}();
        }

        return $factory->create($attributes);
    }
}
