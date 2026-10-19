<?php

namespace App\Http\Requests\Drought;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Drought\Restriction;
use Illuminate\Validation\Rule;

class StoreRestrictionRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'type' => ['required', Rule::in(array_keys(Restriction::TYPES))],
            'titre' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'usages_interdits' => ['nullable', 'string', 'max:2000'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'statut' => ['required', Rule::in(array_keys(Restriction::STATUTS))],
        ];
    }
}
