<?php

namespace App\Http\Requests\Drought;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Drought\ScheduledCut;
use Illuminate\Validation\Rule;

class StoreScheduledCutRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'infrastructure_id' => ['nullable', 'integer', 'exists:infrastructures,id'],
            'motif' => ['required', 'string', 'max:180'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'statut' => ['required', Rule::in(array_keys(ScheduledCut::STATUTS))],
        ];
    }
}
