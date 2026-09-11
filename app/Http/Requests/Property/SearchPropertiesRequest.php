<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;

class SearchPropertiesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:255'],
            'city' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function guests(): int
    {
        return (int)($this->validated('guests') ?? 1);
    }

    public function perPage(): int
    {
        return (int)($this->validated('per_page') ?? 15);
    }
}
