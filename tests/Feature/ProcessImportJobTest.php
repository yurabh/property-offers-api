<?php

namespace Tests\Feature;

use App\Actions\Import\ProcessImport;
use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProcessImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_properties_and_offers_and_completes(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()
            ->for($supplier)
            ->withOffers([$this->offerRow(), $this->offerRow(['external_id' => 'offer-a-10002'])])
            ->create(['sent_at' => '2026-09-01T10:00:00Z']);

        $this->runJob($import);

        $import->refresh();
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->processed_offers);
        $this->assertSame(2, $import->total_offers);
        $this->assertNotNull($import->completed_at);
        $this->assertNull($import->error);

        $this->assertSame(1, Property::count());
        $this->assertSame(2, Offer::count());

        $offer = Offer::where('external_id', 'offer-a-10001')->sole();
        $this->assertSame(72500, $offer->price);
        $this->assertSame($import->id, $offer->import_id);
        $this->assertSame('Barcelona', $offer->property->city);
    }

    public function test_an_offer_from_another_import_is_updated_not_duplicated(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $first = Import::factory()->for($supplier)
            ->withOffers([$this->offerRow()])
            ->create(['sent_at' => '2026-09-01T10:00:00Z']);
        $this->runJob($first);

        $second = Import::factory()->for($supplier)
            ->withOffers([$this->offerRow(['price' => 61000, 'available_units' => 5])])
            ->create(['sent_at' => '2026-09-02T10:00:00Z']);
        $this->runJob($second);

        $this->assertSame(1, Offer::count());

        $offer = Offer::sole();
        $this->assertSame(61000, $offer->price);
        $this->assertSame(5, $offer->available_units);
        $this->assertSame($second->id, $offer->import_id);
    }

    public function test_an_offer_is_updated_by_the_latest_processed_import(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);

        $newer = Import::factory()->for($supplier)
            ->withOffers([$this->offerRow(['price' => 61000])])
            ->create(['sent_at' => '2026-09-02T10:00:00Z']);
        $this->runJob($newer);

        $older = Import::factory()->for($supplier)
            ->withOffers([$this->offerRow(['price' => 99000])])
            ->create(['sent_at' => '2026-09-01T10:00:00Z']);
        $this->runJob($older);

        $this->assertSame(99000, Offer::sole()->price);
        $this->assertSame(ImportStatus::Completed, $older->refresh()->status);
    }

    public function test_running_the_job_twice_changes_nothing(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->for($supplier)
            ->withOffers([$this->offerRow()])
            ->create(['sent_at' => '2026-09-01T10:00:00Z']);

        $this->runJob($import);
        $this->runJob($import);

        $this->assertSame(1, Offer::count());
        $this->assertSame(1, Property::count());
        $this->assertSame(1, $import->refresh()->processed_offers);
    }

    public function test_a_failure_marks_the_import_failed(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->for($supplier)
            ->withOffers([$this->offerRow()])
            ->create(['sent_at' => '2026-09-01T10:00:00Z']);

        $this->mock(ProcessImport::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('supplier feed is broken'));

        try {
            $this->runJob($import);
            $this->fail('Джоба мала прокинути виняток далі, щоб черга зробила retry.');
        } catch (RuntimeException $e) {
            $this->assertSame('supplier feed is broken', $e->getMessage());
        }

        $import->refresh();
        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertSame('supplier feed is broken', $import->error);
        $this->assertNull($import->completed_at);
    }

    private function runJob(Import $import): void
    {
        (new ProcessImportJob($import))->handle(app(ProcessImport::class));
    }

    private function offerRow(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }
}
