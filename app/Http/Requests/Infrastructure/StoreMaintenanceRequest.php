<?php

namespace App\Http\Requests\Infrastructure;

use App\Enums\UserRole;
use App\Http\Requests\BackOfficeRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'infrastructure_id' => ['required', 'integer', 'exists:infrastructures,id'],
            'technicien_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereIn('role', UserRole::backOfficeValues()),
            ],
            'type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:16000'],
            'date_intervention' => ['required', 'date'],
            'cout' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'statut' => ['required', 'string', 'max:255'],
        ];
    }
}
