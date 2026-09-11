<?php

namespace App\Jobs;

use App\Actions\Import\ProcessImport;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Import $import)
    {
    }

    public function uniqueId(): string
    {
        return (string)$this->import->id;
    }

    public function handle(ProcessImport $processImport): void
    {
        $import = $this->import->fresh();

        if ($import === null || $import->status->isCompleted()) {
            return;
        }

        $import->markProcessing();

        try {
            $processImport->handle($import);
        } catch (Throwable $e) {
            $import->markFailed($e->getMessage());
            throw $e;
        }

        $import->markCompleted();
    }

    public function failed(Throwable $e): void
    {
        $this->import->fresh()?->markFailed($e->getMessage());
    }
}
