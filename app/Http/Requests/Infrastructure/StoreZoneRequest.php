<?php

namespace App\Http\Requests\Infrastructure;

use App\Http\Requests\BackOfficeRequest;

class StoreZoneRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'commune' => ['required', 'string', 'max:255'],
            'code_postal' => ['required', 'string', 'max:10'],
            'population' => ['nullable', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
