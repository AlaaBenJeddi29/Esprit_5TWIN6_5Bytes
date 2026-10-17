<?php

namespace App\Http\Requests\Infrastructure;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Infrastructure\Infrastructure;
use Illuminate\Validation\Rule;

class StoreInfrastructureRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Infrastructure::TYPES)],
            'materiau' => ['nullable', 'string', 'max:255'],
            'date_installation' => ['nullable', 'date'],
            'capacite' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'statut' => ['required', Rule::in(Infrastructure::STATUTS)],
            'score_risque' => ['nullable', 'numeric', 'between:0,999.99'],
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
        ];
    }
}
