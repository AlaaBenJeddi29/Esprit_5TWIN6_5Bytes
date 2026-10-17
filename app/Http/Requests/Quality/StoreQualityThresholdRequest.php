<?php

namespace App\Http\Requests\Quality;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Quality\QualityThreshold;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQualityThresholdRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'parametre' => ['required', Rule::in(array_keys(QualityThreshold::PARAMETRES))],
            'unite' => ['required', 'string', 'max:20'],
            'valeur_min' => ['nullable', 'numeric'],
            'valeur_max' => ['nullable', 'numeric'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $min = $this->input('valeur_min');
                $max = $this->input('valeur_max');

                if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $min > (float) $max) {
                    $validator->errors()->add('valeur_min', 'La valeur minimale doit être inférieure ou égale à la valeur maximale.');
                }

                if (($min === null || $min === '') && ($max === null || $max === '')) {
                    $validator->errors()->add('valeur_min', 'Au moins une borne (minimale ou maximale) doit être renseignée.');
                }

                $zoneId = $this->input('zone_id');
                $existing = QualityThreshold::query()
                    ->where('parametre', $this->input('parametre'))
                    ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId), fn ($query) => $query->whereNull('zone_id'))
                    ->when($this->route('threshold'), fn ($query, $threshold) => $query->whereKeyNot($threshold->getKey()))
                    ->exists();

                if ($existing) {
                    $validator->errors()->add('parametre', 'Un seuil existe déjà pour ce paramètre et cette zone.');
                }
            },
        ];
    }
}
