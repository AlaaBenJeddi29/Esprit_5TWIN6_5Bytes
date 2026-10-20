<?php

namespace App\Http\Requests\Quality;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Quality\QualityThreshold;
use Illuminate\Validation\Validator;

class StoreWaterQualitySampleRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'infrastructure_id' => ['nullable', 'integer', 'exists:infrastructures,id'],
            'date_prelevement' => ['required', 'date'],
            'source' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'planification_id' => ['nullable', 'integer', 'exists:quality_planifications,id'],
            'parametres' => ['required', 'array:'.implode(',', array_keys(QualityThreshold::PARAMETRES))],
            'parametres.*' => ['array'],
            'parametres.*.valeur' => ['nullable', 'numeric'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $values = $this->parameterRows();

                if ($values === []) {
                    $validator->errors()->add('parametres', 'Saisissez au moins une valeur de paramètre.');
                }
            },
        ];
    }

    /**
     * Valeurs renseignées, limitées aux paramètres connus.
     *
     * @return list<array{parametre: string, valeur: float, unite: string}>
     */
    public function parameterRows(): array
    {
        $rows = [];
        $input = $this->input('parametres', []);

        foreach (array_keys(QualityThreshold::PARAMETRES) as $parametre) {
            $value = $input[$parametre]['valeur'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $rows[] = [
                'parametre' => $parametre,
                'valeur' => (float) $value,
                'unite' => QualityThreshold::UNITES[$parametre] ?? '',
            ];
        }

        return $rows;
    }
}
