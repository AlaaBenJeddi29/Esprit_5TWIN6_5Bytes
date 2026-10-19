<?php

namespace App\Http\Requests\Quality;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Quality\QualityPlanification;
use Illuminate\Validation\Rule;

class StoreQualityPlanificationRequest extends BackOfficeRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'date_prevue' => ['required', 'date'],
            'frequence' => ['required', 'string', Rule::in(array_keys(QualityPlanification::FREQUENCES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
