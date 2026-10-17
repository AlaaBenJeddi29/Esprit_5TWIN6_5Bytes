<?php

namespace App\Http\Requests\Drought;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Drought\ConsumptionReading;
use Illuminate\Validation\Validator;

class StoreConsumptionReadingRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'periode' => ['required', 'date'],
            'consommation_m3' => ['required', 'numeric', 'min:0'],
            'nombre_abonnes' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $reading = $this->route('reading');
                $exists = ConsumptionReading::query()
                    ->where('zone_id', $this->input('zone_id'))
                    ->whereDate('periode', $this->input('periode'))
                    ->when($reading, fn ($query) => $query->whereKeyNot($reading->getKey()))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('periode', 'Un relevé existe déjà pour cette zone et cette période.');
                }
            },
        ];
    }
}
