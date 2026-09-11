<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_both_suppliers_and_stays_idempotent(): void
    {
        $this->seed(SupplierSeeder::class);
        $this->seed(SupplierSeeder::class);

        $this->assertSame(2, Supplier::count());
        $this->assertEqualsCanonicalizing(
            ['supplier-a', 'supplier-b'],
            Supplier::pluck('code')->all(),
        );
    }
}
