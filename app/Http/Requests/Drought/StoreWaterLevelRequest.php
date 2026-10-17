<?php

namespace App\Http\Requests\Drought;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Drought\WaterLevel;
use Illuminate\Validation\Rule;

class StoreWaterLevelRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom_source' => ['required', 'string', 'max:150'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'type_source' => ['required', Rule::in(array_keys(WaterLevel::TYPES))],
            'niveau' => ['required', 'numeric', 'between:0,100'],
            'seuil_alerte' => ['required', 'numeric', 'between:0,100'],
            'seuil_critique' => ['required', 'numeric', 'between:0,100', 'lte:seuil_alerte'],
            'date_mesure' => ['required', 'date'],
        ];
    }
}
