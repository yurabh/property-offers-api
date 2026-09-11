<?php

namespace App\Actions\Property;

use App\Models\Offer;
use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class SearchProperties
{
    public function handle(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->propertiesWithBestOffer($this->bestOfferPerProperty($filters))
            ->paginate($perPage)
            ->withQueryString();
    }

    private function bestOfferPerProperty(array $filters): Builder
    {
        return Offer::query()
            ->join('properties', 'properties.id', '=', 'offers.property_id')
            ->where('offers.check_in', $filters['check_in'])
            ->where('offers.check_out', $filters['check_out'])
            ->where('offers.max_guests', '>=', $filters['guests'])
            ->where('offers.available_units', '>', 0)
            ->where('offers.expires_at', '>', now())
            ->when(
                $filters['city'] ?? null,
                fn($query, string $city) => $query->where('properties.city', $city),
            )
            ->select([
                'offers.id',
                'offers.property_id',
                'offers.supplier_id',
                'offers.price',
                'offers.currency',
                'offers.available_units',
                'offers.expires_at',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY offers.property_id ORDER BY offers.price ASC, offers.id ASC) AS rn'
            );
    }

    private function propertiesWithBestOffer(Builder $bestOffers): Builder
    {
        return Property::query()
            ->joinSub(
                $bestOffers,
                'best',
                fn(JoinClause $join) => $join
                    ->on('best.property_id', '=', 'properties.id')
                    ->where('best.rn', '=', 1),
            )
            ->join('suppliers', 'suppliers.id', '=', 'best.supplier_id')
            ->select([
                'properties.id',
                'properties.code',
                'properties.name',
                'properties.city',
                'best.id as best_offer_id',
                'best.price as best_offer_price',
                'best.currency as best_offer_currency',
                'best.available_units as best_offer_available_units',
                'best.expires_at as best_offer_expires_at',
                'suppliers.code as best_offer_supplier',
            ])
            ->orderBy('best.price')
            ->orderBy('properties.id');
    }
}
