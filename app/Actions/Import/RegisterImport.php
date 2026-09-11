<?php

namespace App\Actions\Import;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use App\Support\SqlState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class RegisterImport
{
    public function handle(array $data): Import
    {
        $supplier = $this->resolveSupplier($data['supplier']);
        $import = $this->firstOrCreateImport($supplier, $data);

        $this->queueProcessing($import);

        return $import;
    }

    private function resolveSupplier(string $code): Supplier
    {
        return Supplier::where('code', $code)->firstOrFail();
    }

    private function firstOrCreateImport(Supplier $supplier, array $data): Import
    {
        $identity = [
            'supplier_id' => $supplier->id,
            'external_import_id' => $data['external_import_id'],
        ];

        try {
            return Import::firstOrCreate($identity, $this->newImportAttributes($data));
        } catch (QueryException $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            return Import::where($identity)->firstOrFail();
        }
    }

    private function newImportAttributes(array $data): array
    {
        return [
            'sent_at' => Carbon::parse($data['sent_at']),
            'status' => ImportStatus::Pending,
            'payload' => ['offers' => $data['offers']],
            'total_offers' => count($data['offers']),
        ];
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return $e->getCode() === SqlState::INTEGRITY_CONSTRAINT_VIOLATION;
    }

    private function queueProcessing(Import $import): void
    {
        if (!$import->wasRecentlyCreated) {
            return;
        }
        ProcessImportJob::dispatch($import);
    }
}
