<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_reservation_and_takes_one_unit(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2, 'price' => 72500]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c')
            ->assertJsonPath('data.customer_name', 'John Smith')
            ->assertJsonPath('data.customer_email', 'john@example.com')
            ->assertJsonPath('data.price', 72500)
            ->assertJsonPath('data.currency', 'EUR');

        $this->assertSame(1, $offer->refresh()->available_units);
        $this->assertSame(1, Reservation::count());
    }

    public function test_repeating_the_same_client_reference_returns_the_existing_reservation(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $first = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated();

        $second = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, $offer->refresh()->available_units, 'Одиниця має списатись рівно один раз.');
    }

    public function test_it_rejects_a_sold_out_offer(): void
    {
        $offer = Offer::factory()->soldOut()->create();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Offer has no available units left.');

        $this->assertSame(0, Reservation::count());
    }

    public function test_it_rejects_an_expired_offer(): void
    {
        $offer = Offer::factory()->expired()->create(['available_units' => 5]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Offer has expired.');

        $this->assertSame(5, $offer->refresh()->available_units);
        $this->assertSame(0, Reservation::count());
    }

    public function test_the_last_unit_can_be_taken_only_once(): void
    {
        $offer = Offer::factory()->create(['available_units' => 1]);

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload())
            ->assertCreated();

        $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload([
            'client_reference' => 'web-order-second',
        ]))->assertStatus(409);

        $this->assertSame(0, $offer->refresh()->available_units);
        $this->assertSame(1, Reservation::count());
    }

    public function test_it_validates_the_payload(): void
    {
        $offer = Offer::factory()->create();

        $this->postJson("/api/offers/{$offer->id}/reservations", ['customer_email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_reference', 'customer_name', 'customer_email']);
    }

    public function test_it_returns_404_for_an_unknown_offer(): void
    {
        $this->postJson('/api/offers/999/reservations', $this->payload())->assertNotFound();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ], $overrides);
    }
}
