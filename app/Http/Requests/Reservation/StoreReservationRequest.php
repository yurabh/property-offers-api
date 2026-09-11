<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_reference' => ['required', 'string', 'max:191'],
            'customer_name' => ['required', 'string', 'max:191'],
            'customer_email' => ['required', 'email', 'max:191'],
        ];
    }
}
