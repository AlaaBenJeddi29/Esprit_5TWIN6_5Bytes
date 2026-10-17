<?php

namespace App\Http\Requests\Quality;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Quality\WaterQualityAlert;
use Illuminate\Validation\Rule;

class UpdateQualityAlertRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in(array_keys(WaterQualityAlert::STATUTS))],
        ];
    }
}
