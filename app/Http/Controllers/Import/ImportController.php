<?php

namespace App\Http\Controllers\Import;

use App\Actions\Import\RegisterImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\StoreImportRequest;
use App\Http\Resources\Import\ImportAcceptedResource;
use App\Http\Resources\Import\ImportResource;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request, RegisterImport $registerImport): JsonResponse
    {
        $import = $registerImport->handle($request->validated());

        return ImportAcceptedResource::make($import)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function show(Import $import): ImportResource
    {
        return ImportResource::make($import->load('supplier'));
    }
}
