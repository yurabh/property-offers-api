<?php

namespace App\Actions\Reservation;

use App\Exceptions\OfferExpiredException;
use App\Exceptions\OfferSoldOutException;
use App\Models\Offer;
use App\Models\Reservation;
use App\Support\SqlState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReserveOffer
{
    public function handle(Offer $offer, array $data): array
    {
        if ($existing = $this->findByReference($data['client_reference'])) {
            return [$existing, false];
        }
        try {
            return [$this->reserveLockedUnit($offer, $data), true];
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e) && $existing = $this->findByReference($data['client_reference'])) {
                return [$existing, false];
            }
            throw $e;
        }
    }

    private function reserveLockedUnit(Offer $offer, array $data): Reservation
    {
        return DB::transaction(function () use ($offer, $data) {
            $locked = $this->lockOffer($offer);

            $this->assertBookable($locked);

            $locked->decrement('available_units');

            return $this->createReservation($locked, $data);
        });
    }

    private function lockOffer(Offer $offer): Offer
    {
        return Offer::whereKey($offer->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertBookable(Offer $offer): void
    {
        if ($offer->isExpired()) {
            throw new OfferExpiredException;
        }

        if (!$offer->hasAvailability()) {
            throw new OfferSoldOutException;
        }
    }

    private function createReservation(Offer $offer, array $data): Reservation
    {
        return Reservation::create([
            'offer_id' => $offer->id,
            'client_reference' => $data['client_reference'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'price' => $offer->price,
            'currency' => $offer->currency,
        ]);
    }

    private function findByReference(string $reference): ?Reservation
    {
        return Reservation::where('client_reference', $reference)->first();
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return $e->getCode() === SqlState::INTEGRITY_CONSTRAINT_VIOLATION;
    }
}
