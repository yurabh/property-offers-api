<?php

namespace App\Http\Controllers\Reservation;

use App\Actions\Reservation\ReserveOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Resources\Reservation\ReservationResource;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReservationController extends Controller
{
    public function __invoke(
        StoreReservationRequest $request,
        Offer                   $offer,
        ReserveOffer            $reserveOffer,
    ): JsonResponse
    {
        [$reservation, $created] = $reserveOffer->handle($offer, $request->validated());
        return ReservationResource::make($reservation)
            ->response()
            ->setStatusCode($created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
