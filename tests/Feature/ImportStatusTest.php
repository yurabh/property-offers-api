<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_current_state_of_an_import(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->create([
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'status' => ImportStatus::Completed,
            'total_offers' => 20,
            'processed_offers' => 20,
            'completed_at' => '2026-09-01T10:00:04Z',
        ]);

        $this->getJson("/api/imports/{$import->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $import->id,
                    'supplier' => 'supplier-a',
                    'external_import_id' => 'import-2026-09-01-001',
                    'sent_at' => '2026-09-01T10:00:00Z',
                    'status' => 'completed',
                    'total_offers' => 20,
                    'processed_offers' => 20,
                    'error' => null,
                    'completed_at' => '2026-09-01T10:00:04Z',
                ],
            ])
            ->assertJsonStructure(['data' => ['created_at']]);
    }

    public function test_it_returns_404_for_an_unknown_import(): void
    {
        $this->getJson('/api/imports/999')->assertNotFound();
    }
}
