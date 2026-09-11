<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_it_accepts_an_import_and_queues_processing(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);

        $response = $this->postJson('/api/imports', $this->payload());

        $response->assertAccepted()
            ->assertJsonPath('data.status', ImportStatus::Pending->value)
            ->assertJsonStructure(['data' => ['id', 'status']]);

        $import = Import::sole();
        $this->assertSame('import-2026-09-01-001', $import->external_import_id);
        $this->assertSame(1, $import->total_offers);
        $this->assertSame(0, $import->processed_offers);

        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_resending_the_same_import_creates_no_duplicate_and_does_not_requeue(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);

        $first = $this->postJson('/api/imports', $this->payload())->assertAccepted();
        $second = $this->postJson('/api/imports', $this->payload())->assertAccepted();

        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
            'Повторний імпорт мусить повернути той самий запис.',
        );
        $this->assertSame(1, Import::count());

        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_the_same_external_id_from_another_supplier_is_a_separate_import(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);
        Supplier::factory()->create(['code' => 'supplier-b']);

        $this->postJson('/api/imports', $this->payload())->assertAccepted();
        $this->postJson('/api/imports', $this->payload(['supplier' => 'supplier-b']))->assertAccepted();

        $this->assertSame(2, Import::count());
        Queue::assertPushed(ProcessImportJob::class, 2);
    }

    public function test_it_rejects_an_unknown_supplier(): void
    {
        $this->postJson('/api/imports', $this->payload(['supplier' => 'supplier-zzz']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('supplier');

        $this->assertSame(0, Import::count());
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_a_malformed_offer(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);

        $payload = $this->payload();
        unset($payload['offers'][0]['price'], $payload['offers'][0]['property']['city']);

        $this->postJson('/api/imports', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offers.0.price', 'offers.0.property.city']);

        Queue::assertNothingPushed();
    }

    public function test_it_rejects_an_import_without_offers(): void
    {
        Supplier::factory()->create(['code' => 'supplier-a']);

        $this->postJson('/api/imports', $this->payload(['offers' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('offers');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 72500,
                    'currency' => 'EUR',
                    'available_units' => 2,
                    'expires_at' => '2026-09-10T23:59:59Z',
                ],
            ],
        ], $overrides);
    }
}
