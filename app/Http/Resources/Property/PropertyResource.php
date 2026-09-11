<?php

namespace App\Http\Resources\Property;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'best_offer' => [
                'id' => (int)$this->best_offer_id,
                'supplier' => $this->best_offer_supplier,
                'price' => (int)$this->best_offer_price,
                'currency' => $this->best_offer_currency,
                'available_units' => (int)$this->best_offer_available_units,
                'expires_at' => Carbon::parse($this->best_offer_expires_at)->toIso8601ZuluString(),
            ],
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $data = $response->getData(true);

        if (isset($data['meta'])) {
            $response->setData([
                'data' => $data['data'] ?? [],
                'next' => $data['links']['next'] ?? null,
                'prev' => $data['links']['prev'] ?? null,
                'per_page' => (int)($data['meta']['per_page'] ?? $request->query('per_page', 15)),
            ]);
        }
    }
}
